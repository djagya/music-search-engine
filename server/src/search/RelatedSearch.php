<?php

namespace Search\search;

use Search\EsClient;
use Search\Indexes;

class RelatedSearch extends BaseSearch
{

    public function search(string $query = null): array
    {
        if (!$this->emptyFields) {
            return ['fields' => [], 'data' => $this->getMatchedData()];
        }

        // todo: for now try to do one request per field. then it's easier to sort
        $result = [];
        foreach ($this->emptyFields as $emptyField) {
            $aggs = [
                'groupByName' => [
                    'terms' => [
                        'field' => "$emptyField.norm",
                        //'order' => ["_count" => 'desc', "$emptyField.sort" => 'asc'],
                        'order' => ["_count" => 'desc'],
                        // 'size' => 100, // amount of unique suggestions to return
                    ],
                    'aggs' => [
                        // Return the top document to get a display value from its '.raw' field.
                        'topHits' => [
                            'top_hits' => [
                                'size' => 1,
                            ],
                        ],
                    ],
                ],
            ];
            $body = [
                // The query body.
                'query' => [
                    'bool' => [
                        'filter' => $this->getSelectedFieldsFilter(),
                        'match_all' => [],
                    ],
                ],
                'aggs' => $aggs,
                'size' => 0, // ignore hits
            ];

            // todo: concurrent queries
            $result[$emptyField] = EsClient::build()->search([
                'index' => $this->index ?: implode(',', [Indexes::EPF_IDX, Indexes::SPINS_IDX]),
                'body' => $body,
            ]);

            if (!$this->withMeta) {
                $result[$emptyField] = $this->formatFieldSuggestions($emptyField, $result[$emptyField]);
            }
        }

        // todo: figure out how to provide data we were able to get when few selected values matched
        return ['fields' => $result, 'data' => ''];
    }

    protected function getMatchedData(): array
    {
        // todo: for now take the first. ideally show e.g. 3 suggested variants;

        $data = EsClient::build()->search([
            'index' => $this->index ?: implode(',', [Indexes::EPF_IDX, Indexes::SPINS_IDX]),
            'body' => [
                'query' => [
                    'bool' => [
                        'filter' => $this->getSelectedFieldsFilter(),
                        'match_all' => [],
                    ],
                ],
                'sort' => [
                    ['_id' => 'desc'],
                ],
            ],
        ]);

        if ($this->withMeta) {
            return $data;
        }

        return array_map(function (array $item) {
            return $item;
        }, $data['hits']['hits']);
    }

    protected function formatFieldSuggestions(string $field, array $data): array
    {
        $suggestions = array_map(function (array $item) use ($field) {
            $hits = $item['topHits']['hits'];
            $hit = $hits['hits'][0];

            return [
                'value' => $hit['_source'][$field],
                'score' => $hits['max_score'],
                'count' => $item['doc_count'],

                // fixme: Maybe not needed
                'id' => $hit['_id'],
                '_index' => $hit['_index'],
            ];
        }, $data['aggregations']['groupByName']['buckets']);

        return [
            'maxScore' => 0,
            'total' => $data['hits']['total'],
            'suggestions' => $suggestions,
        ];
    }

    protected function formatSuggestionsOneQuery(array $data): array
    {
        // todo: need to keep the related suggestions togethter
        $aggs = $data['aggregations']['relatedGroups']['buckets'];

        return array_map(function (array $item) {
            return [

            ];
        }, $aggs);
    }

    protected function formatSuggestions(array $data): array
    {
        // TODO: Implement formatSuggestions() method.
    }

    /**
     *
     * todo: for now it retusn one list hits, but for client it's better to have one list per $emptyField each
     * following the same format as Suggestion
     * @param string|null $query
     * @return array
     */
    public function searchOneQuery(string $query = null): array
    {
        // Multiple sources, one per empty field, for the composite aggregation.
        $sources = [];
        foreach ($this->emptyFields as $field) {
            $prop = "$field.norm";

            // todo: need to aggregate ignoring case of returned hits. take item what name variation in the group? probably just the first in a bucket
            // todo: maybe use script? some way of normalizing?
            // todo: related should be sorted by most frequent first
            $sources[] = [$field => ['terms' => ['field' => $prop]]];
        }

        // todo: sort using ".sort" field
        // fixme: composite key might be not a good choice because the list of results might have just one artist and many many his songs
        $aggs = [
            'relatedGroups' => [
                'composite' => [
                    'sources' => $sources,
                ],
            ],
        ];

        $body = [
            // The query body.
            'query' => [
                'bool' => [
                    'filter' => $this->getSelectedFieldsFilter(),
                    'match_all' => [],
                ],
            ],
            'aggs' => $aggs,
            'size' => 0, // ignore hits
        ];

        $result = EsClient::build()->search([
            'index' => $this->index ?: implode(',', [Indexes::EPF_IDX, Indexes::SPINS_IDX]),
            'body' => $body,
            '_source' => $this->withMeta
                ? null
                : array_merge(['id'], array_map(function (string $field) {
                    return self::AC_FIELDS[$field];
                }, array_merge($this->emptyFields, array_keys($this->selectedFields)))),
        ]);

        return $this->formatResponse($result);
    }
}
