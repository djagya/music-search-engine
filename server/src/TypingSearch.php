<?php

namespace Search;

class TypingSearch
{
    const FIELD_COLUMN_MAP = [
        'artist' => 'artist_name',
        'song' => 'song_name',
        'release' => 'release_title',
        'composer' => 'song_composer',
    ];

    protected $field;
    protected $withMeta = true;

    public function __construct(string $field, bool $withMeta = true)
    {
        if (!array_key_exists($field, self::FIELD_COLUMN_MAP)) {
            throw new \InvalidArgumentException("Invalid field {$field}");
        }
        $this->field = $field;
        $this->withMeta = $withMeta;
    }

    /**
     * todo: how do we return the result?
     * todo: describe aggregation and why we need it here.
     *
     * The result data contains the auto-complete suggestions for the given query and a list of ids which match the suggestion.
     * For example for the $field = "song" and $query = "Love", a set of items is returned:
     * [
     *  [ids => [pk1, pk2, ...], $column => 'love'],
     *  [ids => [pk3, pk4, ...], $column => 'live'],
     *  [ids => [pk5, pk6, ...], $column => 'lone'],
     *  ...
     * ]
     *
     * todo: Describe that we don't want to build a list of related ids unless user selects on of the suggestions.
     * related => [
     *      release => [pk1, pk7, ...],
     *      artist => [pk1, pk4, ...],
     *      composer => [pk4, pk8, ...],
     *  ]
     *
     * todo: how to represent pk? Once we introduce EPF data, items might come from different indexes, so a pk would be (index name, id).
     *
     * todo: what type of search do we use?
     * should it be a suggester? on low level it would be a combination of: 1) exact phrase match, 2) term match, 3) fuzzy match
     * since we have Unicode, we'll use trigrams probably?
     *
     * todo: don't run search on $query length <=2 ?
     *
     * @param string $query
     * @return array
     */
    public function search(string $query): array
    {
        $column = self::FIELD_COLUMN_MAP[$this->field];
        $select = ['id', $column];
        $body = [
            'query' => [
                'match' => [
                    $column => $query
                ],
            ],
        ];

        $result = EsClient::build()->search([
            'index' => 'spins',
            'body' => $body,
            '_source' => $select,
        ]);

        return $this->formatResult($result);
    }

    /**
     * todo: decide what format is suitable for the client
     *
     * @param array $result
     * @return array
     */
    protected function formatResult(array $result): array
    {
        if ($this->withMeta) {
            return $result;
        }

        $hits = $result['hits']['hits'];
        $maxScore = $result['hits']['max_score'];
        $total = $result['hits']['total'];


        return [
            'maxScore' => $maxScore,
            'total' => $total,
            'hits' => array_map(\Closure::fromCallable([$this, 'formatHit']), $hits),
        ];
    }

    protected function formatHit(array $item): array
    {
        return [
            '_id' => $item['_id'],
            '_index' => $item['_index'],
            '_score' => $item['_score'],
            'id' => $item['_source']['id'],
            'value' => $item['_source'][self::FIELD_COLUMN_MAP[$this->field]],
        ];
    }
}
