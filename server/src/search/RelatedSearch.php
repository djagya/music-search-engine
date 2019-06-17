<?php

namespace app\search;

use app\EsClient;

class RelatedSearch extends BaseSearch
{
    public function search(string $query = null): array
    {
        if (!$this->emptyFields) {
            return ['fields' => [], 'data' => $this->getMatchedData()];
        }

        // todo: for now try to do one request per field. then it's easier to sort
        $fieldResponses = [];
        foreach ($this->emptyFields as $emptyField) {
            $aggs = [
                'groupByName' => [
                    'terms' => [
                        'field' => "$emptyField.norm",
                        // Sorting by docs count is better as frequent values are more likely to be searched for.
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

            $params = [
                'index' => $this->getIndexName(),
                'body' => [
                    'query' => [
                        'constant_score' => [
                            'filter' => $this->getSelectedFieldsFilter(),
                        ],
                    ],
                    'aggs' => $aggs,
                    'size' => 0, // ignore hits
                ],
            ];

            $this->logger->debug("Related query [$emptyField] params", $params);

            // todo: concurrent queries
            $t = microtime(true);
            $result = EsClient::build(true)->search($params);
            $tookMs = (microtime(true) - $t) * 1000;
            $this->logger->info("Related query [$emptyField] took {$tookMs}ms");

            // Pass the current searched field so formatSuggestions know what value to take for a suggestion.
            $result['field'] = $emptyField;
            $fieldResponses[$emptyField] = $this->formatResponse($result, $tookMs);
        }

        // todo: figure out how to provide data we were able to get when few selected values matched
        return ['fields' => $fieldResponses, 'data' => ''];
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

    protected function getMatchedData(): array
    {
        // todo: for now take the first. ideally show e.g. 3 suggested variants;
        $data = EsClient::build()->search([
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
            ],
        ]);

        if ($this->withMeta) {
            return $data;
        }

        return array_map(function (array $item) {
            return $item;
        }, $data['hits']['hits']);
    }

}
