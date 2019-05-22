<?php

namespace Search;

use Closure;

abstract class BaseSearch
{
    const FIELD_COLUMN_MAP = [
        'artist' => 'artist_name',
        'song' => 'song_name',
        'release' => 'release_title',
        'composer' => 'song_composer',
    ];

    /** @var string[] list of field to suggest */
    protected $emptyFields = [];
    /** @var array [field => value] map of already selected fields and their values */
    protected $selectedFields = [];
    protected $withMeta = true;

    /**
     * BaseSearch constructor.
     * @param string[] $emptyFields
     * @param array $selected [field => value]
     * @param bool $withMeta
     */
    public function __construct(array $emptyFields, array $selected = [], bool $withMeta = true)
    {
        $this->emptyFields = $emptyFields;
        $this->selectedFields = $selected;
        $this->withMeta = $withMeta;
    }

    abstract public function search(string $query): array;

    /**
     * @param array $result
     * @return array
     */
    protected function formatResult(array $result): array
    {
        if ($this->withMeta) {
            return $result;
        }

        $hits = $result['hits']['hits'];
        $aggregations = $result['aggregations'];
        $maxScore = $result['hits']['max_score'];
        $total = $result['hits']['total'];

        $uniqueValues = $aggregations['uniqueValue']['buckets'];

        return [
            'maxScore' => $maxScore,
            'total' => $total,
            'hits' => array_map(Closure::fromCallable([$this, 'formatHit']), $hits),
            'aggregations' => array_map(Closure::fromCallable([$this, 'formatAggregation']),
                $uniqueValues),
        ];
    }

    protected function formatHit(array $item): array
    {
        $values = [];
        foreach ($item['_source'] as $column => $value) {
            $field = array_flip(self::FIELD_COLUMN_MAP)[$column] ?? null;
            $values[$field] = $value;
        }

        return [
            // renamed _id contains the EPF or spins id
            '_id' => $item['_source']['id'],
            '_index' => $item['_index'],

            // Use ES id as identification, no need to leak EPF or spins ids to the client.
            'id' => $item['_id'],
            'score' => $this->formatScore($item['_score']),
            'count' => 1,
            'values' => $values,
        ];
    }

    protected function formatAggregation(array $item): array
    {
        return [
            'id' => uniqid('', true),
            'value' => $item['key'],
            'score' => $this->formatScore($item['maxScore']['value'] ?? 0),
            'count' => $item['doc_count'],
        ];
    }

    protected function formatScore(float $score): string
    {
        return sprintf('%0.2f', $score);
    }
}
