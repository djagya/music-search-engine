<?php

namespace app\search;

class RelatedSearch extends BaseSearch
{
    public function search(string $query = null): array
    {
        $fieldResponses = [];
        foreach ($this->emptyFields as $emptyField) {
            $result = $this->getFieldSuggestions($emptyField);
            // Pass the current searched field so formatSuggestions know what value to take for a suggestion.
            $result['field'] = $emptyField;
            $fieldResponses[$emptyField] = $this->formatResponse($result);
        }

        return ['fields' => $fieldResponses, 'data' => count($this->selectedFields) > 1 ? $this->getMatchedData() : []];
    }

    protected function formatSuggestions(array $data): array
    {
        // For what field is the current list of suggestions?
        $field = $data['field'];

        return array_map(function (array $item) use ($field) {
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
    }

    protected function getFieldSuggestions(string $field): array
    {
        $params = [
            'index' => $this->getIndexName(),
            'body' => [
                'size' => 0, // ignore hits
                'query' => ['constant_score' => ['filter' => $this->getSelectedFieldsFilter()]],
                'aggs' => [
                    'groupByName' => [
                        'terms' => [
                            'field' => "$field.norm",
                            // Sorting by docs count is better as frequent values are more likely to be searched for.
                            'order' => ["_count" => 'desc'],
                            // 'size' => 100, // amount of unique suggestions to return
                        ],
                        'aggs' => [
                            // Return the top document to get a display value from its field.
                            'topHits' => ['top_hits' => ['size' => 1]],
                        ],
                    ],
                    'totalCount' => ['cardinality' => ['field' => "$field.norm"]],
                ],
            ],
        ];

        // todo: concurrent queries
        $this->logParams("Related [$field] body", $params);
        $result = $this->client->search($params);
        $this->logger->info("Related [$field] took {$result['took']}ms");

        return $result;
    }

    protected function getMatchedData(): array
    {
        $data = $this->client->search([
            'index' => $this->getIndexName(),
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
                'size' => 50,
            ],
        ]);

        if ($this->withDebug) {
            return $data;
        }

        return array_map(function (array $item) {
            return array_merge($item['_source'], [
                '_index' => $item['_index'],
                '_id' => $item['_id'],
            ]);
        }, $data['hits']['hits']);
    }
}
