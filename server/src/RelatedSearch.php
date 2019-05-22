<?php

namespace Search;

class RelatedSearch extends BaseSearch
{
    // todo: decide whether do the data processing on client or server:
    // probably need to sort results differently for each suggested field?
    //
    // So it seems it's better to transform the result into lists for every empty field.
    /**
     *
     * todo: for now it retusn one list hits, but for client it's better to have one list per $emptyField each
     * following the same format as Suggestion
     * @param string|null $query
     * @return array
     */
    public function search(string $query = null): array
    {
        // Filter by selected.
        // todo: probably filter not by raw but by
        $selectedMatch = [];
        foreach ($this->selectedFields as $field => $value) {
            $column = self::FIELD_COLUMN_MAP[$field];
            $selectedMatch[] = ['term' => ["$column.raw" => $value]];
        }

        $body = [
            // The query body.
            'query' => [
                'bool' => [
                    'filter' => $selectedMatch,
                    'match_all' => [],
                ],
            ],
            'aggs' => [
                // Bucket results based on the original value - makes a list of unique $column name buckets.
                // "term" type aggregation takes "size" top results and make unique buckets.
                'uniqueValue' => [
                    'terms' => [
                        // todo: need to aggregate ignoring case of returned hits. take item what name variation in the group? probably just the first in a bucket
                        'field' => 'artist_name.raw',
                        'order' => ['maxScore' => 'desc'],
                    ],
                    // Nested aggregation to make ordering by best match score possible.
                    'aggs' => [
                        'maxScore' => [
                            'max' => [
                                'script' => ['source' => '_score'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = EsClient::build()->search([
            'index' => 'spins',
            'body' => $body,
            '_source' => $this->withMeta
                ? null
                : array_merge(['id'], array_map(function (string $field) {
                    return self::FIELD_COLUMN_MAP[$field];
                }, array_merge($this->emptyFields, array_keys($this->selectedFields)))),
            // todo: set the limit?
        ]);

        return $this->formatResult($result);
    }
}
