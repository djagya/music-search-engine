<?php

namespace app\search;

use app\Indexes;
use app\Logger;

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
    /** @var \Monolog\Logger */
    protected $logger;

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
        $this->logger = Logger::get('search');
    }

    abstract public function search(string $query): array;

    abstract protected function formatSuggestions(array $data): array;

    protected function getIndexName(): string
    {
        return $this->index ?: implode(',', [Indexes::EPF_IDX, Indexes::SPINS_IDX]);
    }

    /**
     * Return the query condition based on already specified fields, so only related to these field values suggestions
     * are returned.
     * Use `.norm` sub-field to ignore the case but preserve the spelling.
     */
    protected function getSelectedFieldsFilter(): array
    {
        $selectedMatch = [];
        foreach ($this->selectedFields as $field => $value) {
            $selectedMatch[] = ['term' => ["$field.norm" => $value]];
        }

        return ['bool' => ['must' => $selectedMatch]];
    }

    /**
     * Format the ES search result to be consumed by the web application.
     */
    protected function formatResponse(array $result): array
    {
        if ($this->withMeta) {
            return $result;
        }

        return [
            'tookMs' => $result['took'],
            'maxScore' => 0,
            'total' => $result['hits']['total'],
            'suggestions' => $this->formatSuggestions($result),
        ];
    }

    protected function logParams(string $message, $params): void
    {
        $this->logger->info($message, ['params' => json_encode($params)]);
    }
}
