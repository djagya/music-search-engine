<?php

namespace Search;

class TypingSearch extends BaseSearch
{
    const FIELD_COLUMN_MAP = [
        'artist' => 'artist_name',
        'song' => 'song_name',
        'release' => 'release_title',
        'composer' => 'song_composer',
    ];

    protected $field;
    protected $withMeta = true;

    public function __construct(string $field, array $selected = [], bool $withMeta = true)
    {
        if (!array_key_exists($field, self::FIELD_COLUMN_MAP)) {
            throw new \InvalidArgumentException("Invalid field {$field}");
        }
        $this->field = $field;

        parent::__construct([$field], $selected, $withMeta);
    }

    /**
     * todo: how do we return the result?
     *
     * The result data contains the auto-complete suggestions for the given query and a list of ids which match the
     * suggestion. For example for the $field = "song" and $query = "Love", a set of items is returned:
     * [
     *  [ids => [pk1, pk2, ...], $column => 'love'],
     *  [ids => [pk3, pk4, ...], $column => 'live'],
     *  [ids => [pk5, pk6, ...], $column => 'lone'],
     *  ...
     * ]
     *
     * todo: how to represent pk? Once we introduce EPF data, items might come from different indexes, so a pk would be
     * (index name, id).
     *
     * todo: what type of search do we use? follow the guide.
     *
     * todo: don't run search on $query length <=2 ?
     *
     * @param string $query
     * @return array
     */
    public function search(string $query): array
    {
        $column = self::FIELD_COLUMN_MAP[$this->field];

        // Filter by selected.
        // todo: probably filter not by raw but by
        $selectedMatch = [];
        foreach ($this->selectedFields as $field => $value) {
            $column = self::FIELD_COLUMN_MAP[$field];
            $selectedMatch[] = ['term' => ["$column.raw" => $value]];
        }

        // Match by typed $query.
        $match = [
            'match' => [$column => $query],
        ];

        // Match on the typingResponse field and filter by selected fields if they are specified.
        $query = $selectedMatch ? [
            'bool' => [
                'filter' => $selectedMatch,
                'must' => [$match],
            ],
        ] : $match;

        $body = [
            // The query body.
            'query' => $query,
            'aggs' => [
                // Bucket results based on the original value - makes a list of unique $column name buckets.
                // "term" type aggregation takes "size" top results and make unique buckets.
                'uniqueValue' => [
                    'terms' => [
                        // todo: need to aggregate ignoring case of returned hits. take item what name variation in the group? probably just the first in a bucket
                        // todo: or have another field for lowercase, aggregate by it but show the first of bucket
                        'field' => $column . '.raw',
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
            '_source' => ['id', $column],
            // todo: set the limit?
        ]);

        return $this->formatResult($result);
    }

    protected function formatHit(array $item): array
    {
        return array_merge(parent::formatHit($item), [
            'value' => $item['_source'][self::FIELD_COLUMN_MAP[$this->field]],
        ]);
    }

}
