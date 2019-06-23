<?php

namespace app\search;

use app\EsClient;
use InvalidArgumentException;

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
     * suggestion. For example for the $field = "song" and $query = "Love", a set of items is returned:
     * [
     *
     *  ...
     * ]
     *
     * todo: don't run search on $query length <=2 ?
     *
     * @param string $query
     * @return array
     */
    public function search(string $query): array
    {
        $field = $this->field;

        // Match the root field (that is prepared to be search for autocomplete suggestions) by the query.
        // todo: when searching "dav", first is "david davenport" because it contains two "dav".
        $match = [
            'match' => [
                $field => [
                    'query' => $query,
                    'operator' => 'and',
                    // AND is needed so when searching multiple words query - no results with only one of the words are returned (e.g. for "amen co" we don't want result with only "co").
                    'analyzer' => 'acQueryAnalyzer',
                    // use the analyzer without n-grams, so we search using only whole terms of the query

                    // support misspelling. AUTO:3:6. length < 3 - exact match, 3..5 - one edit allowed, >6 - two edits
                    // todo: fuziness can be slow for our data. then we'll need trigrams?
                    'fuzziness' => 'auto',
                    'prefix_length' => 3,
                    // todo: cover in bachelor difference in performance with prefix = 1 or 2 or 3. or no prefix
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
                // todo: sort values with one score by name length
                'aggs' => [
                    'groupByName' => [
                        'terms' => [
                            'field' => "$field.norm",
                            'order' => ['maxScore' => 'desc'], // best match first
                            // 'size' => 100, // amount of unique suggestions to return
                        ],
                        'aggs' => [
                            // Aggregate the bucket max score for sorting.
                            'maxScore' => ['max' => ['script' => ['source' => '_score']]],
                            // Return the top document to get a display value.
                            'topHits' => [
                                'top_hits' => [
                                    'size' => 1,
                                ],
                            ],
                        ],
                    ],
                    'totalCount' => [
                        'cardinality' => ['field' => "$field.norm"],
                    ],
                ],
                'size' => 0, // don't return search hits, because we work with aggregated buckets only
                // todo: also maybe implement search cancellation when a new request was received
                'timeout' => '10s',
            ],
        ];

        $silent = getenv('SILENT') === 1;

        if (!$silent) {
            $this->logParams("Typing [$query] body", $params);
            $result = EsClient::build(true)->search($params);
            $this->logger->info("Typing [$query] took {$result['took']}ms");
        } else {
            $result = EsClient::build()->search($params);
        }

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

                // fixme: Maybe not needed
                'id' => $hit['_id'],
                '_index' => $hit['_index'],
            ];
        }, $suggestions);
    }
}
