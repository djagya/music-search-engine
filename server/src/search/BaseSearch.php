<?php

namespace Search\search;

abstract class BaseSearch
{
    const AC_FIELDS = [
        'artist_name',
        'song_name',
        'release_title',
    ];

    /** @var string[] list of field to suggest */
    protected $emptyFields = [];
    /** @var array [field => value] map of already selected fields and their values */
    protected $selectedFields = [];
    protected $withMeta = true;
    /** @var string optional index name to force searching only within that index */
    protected $index;

    /**
     * BaseSearch constructor.
     * @param string[] $emptyFields
     * @param array $selected [field => value]
     * @param bool $withMeta
     * @param string $index
     */
    public function __construct(array $emptyFields, array $selected = [], bool $withMeta = true, string $index = null)
    {
        $this->emptyFields = array_filter($emptyFields);
        $this->selectedFields = $selected;
        $this->withMeta = $withMeta;
        $this->index = $index;
    }

    abstract public function search(string $query): array;

    /**
     * Return the match query based on already selected fields to search only for related suggestions.
     * Use `.norm` sub-field to ignore the case but preserve the spelling.
     * todo: not sure, maybe we should fetch data ignoring the spelling too, so there's a broader set of results if
     * there was a typo in query
     */
    protected function getSelectedFieldsFilter(): array
    {
        $selectedMatch = [];
        foreach ($this->selectedFields as $field => $value) {
            $selectedMatch[] = ['term' => ["$field.norm" => $value]];
        }

        return $selectedMatch;
    }

    protected function formatResponse(array $result): array
    {
        if ($this->withMeta) {
            return $result;
        }

        return [
            'maxScore' => 0,
            'total' => $result['hits']['total'],
            'suggestions' => $this->formatSuggestions($result),
        ];
    }

    abstract protected function formatSuggestions(array $data): array;

    protected function formatHit(array $item): array
    {
        return [
            // renamed _id contains the EPF or spins id
            '_id' => $item['_source']['id'] ?? $item['_id'],
            '_index' => $item['_index'],

            // Use ES id as identification, no need to leak EPF or spins ids to the client.
            'id' => $item['_id'],
            'score' => $this->formatScore($item['_score']),
            'count' => 1,
            'values' => [],
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
