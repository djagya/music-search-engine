<?php

namespace app\search;

use InvalidArgumentException;

/**
 * This search model returns a list of autocomplete suggestions for a field based on a typed query.
 */
class TypingSearch extends BaseSearch
{
    protected $field;

    public function __construct(string $field, array $selected = [], bool $withDebug = true, string $index = null)
    {
        if (!in_array($field, self::AC_FIELDS)) {
            throw new InvalidArgumentException("Invalid field {$field}");
        }
        $this->field = $field;

        parent::__construct([$field], $selected, $withDebug, $index);
    }

    /**
     * The result data contains the auto-complete suggestions for the given query and a list of ids which match the
     * suggestion.
     * @param string $query
     * @return array
     */
    public function search(string $query): array
    {
        $field = $this->field;

        // Match the root field (that is prepared to be search for autocomplete suggestions) by the query.
        $match = [
            'match' => [
                $field => [
                    'query' => $query,
                    'operator' => 'and',
                    // AND is needed so when searching multiple words query - no results with only one of the words are returned (e.g. for "amen co" we don't want result with only "co").
                    // support misspelling. AUTO:3:6. length < 3 - exact match, 3..5 - one edit allowed, >6 - two edits
                    'fuzziness' => 'auto',
                ],
            ],
        ];
        // Search only for related suggestions if some field are already selected.
        $selectedFilter = $this->getSelectedFieldsFilter();

        // Match on the typingResponse field and filter by selected fields if they are specified.
        $queryBody = $selectedFilter ? [
            'bool' => [
                'filter' => $selectedFilter,
                'must' => [$match],
                // Boost score for exact matches.
                'should' => [
                    ['match' => [$field => $query]],
                ],
            ],
        ] : $match;

        $params = [
            'index' => $this->getIndexName(),
            'body' => [
                'query' => $queryBody,
                'aggs' => [
                    'groupByName' => [
                        'terms' => [
                            'field' => "$field.norm",
                            'order' => ['maxScore' => 'desc'], // best match first
                            'size' => 50, // amount of unique suggestions to return
                        ],
                        'aggs' => [
                            // Aggregate the bucket max score for sorting.
                            'maxScore' => ['max' => ['script' => ['source' => '_score']]],
                            // Return the top document to get a display value.
                            'topHits' => ['top_hits' => ['size' => 1]],
                        ],
                    ],
                    'totalCount' => ['cardinality' => ['field' => "$field.norm"]],
                ],
            ],
            'size' => 0, // don't return search hits, because we work with aggregated buckets only
            'timeout' => '10s',
        ];

        $this->logParams("Typing [$query] body", $params);
        $result = $this->client->search($params);
        $this->logger->info("Typing [$query] took {$result['took']}ms");

        return $this->formatResponse($result);
    }

    protected function formatSuggestions(array $data): array
    {
        $suggestions = $data['aggregations']['groupByName']['buckets'];

        return array_map(function (array $item) {
            $hits = $item['topHits']['hits'];
            $hit = $hits['hits'][0];

            return [
                'value' => $hit['_source'][$this->field],
                'score' => $hits['max_score'],
                'count' => $item['doc_count'],
                'id' => $hit['_id'],
                '_index' => $hit['_index'],
            ];
        }, $suggestions);
    }
}
