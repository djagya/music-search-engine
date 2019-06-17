<?php

namespace app\search;

use app\EsClient;
use app\Indexes;

class ChartSearch
{
    const PAGE_SIZE = 50;

    const TYPE_SONG = 'song';
    const TYPE_ARTIST = 'artist';
    const TYPE_RELEASE = 'release';

    protected $type = self::TYPE_SONG;
    protected $chartMode = false;
    protected $meta = false;

    public function __construct(string $type, bool $chartMode, bool $meta = false)
    {
        $this->type = $type;
        $this->chartMode = $chartMode;
        $this->meta = $meta;
    }

    /**
     * @param array $query array of fields values to use in filter query
     * @param array $params pagination and other params
     * @return array rows for the current page
     */
    public function search(array $query, array $params = ['page' => 0]): array
    {
        $data = null;
        if ($this->type === self::TYPE_SONG) {
            $data = $this->searchSongs($query, $params['page']);
        } elseif ($this->type === self::TYPE_ARTIST) {
            $data = $this->searchArtists($query);
        } elseif ($this->type === self::TYPE_RELEASE) {
            $data = $this->searchReleases($query);
        }

        if ($this->meta) {
            return $data;
        }

        return [
            'totalCount' => $data['totalCount'],
            'page' => $params['page'],
            'rows' => $data['rows'],
        ];
    }

    // todo: here only prefix matching is supported
    protected function searchSongs(array $query, int $page): array
    {
        $from = $page * self::PAGE_SIZE;

        // Generate query for fields with supported prefix search (main AC fields). Use root indexed field.
        $fullTextQuery = [];
        foreach (BaseSearch::AC_FIELDS as $fullTextField) {
            if (!empty($query[$fullTextField])) {
                $fullTextQuery[] = ['match' => [$fullTextField => $query[$fullTextField]]];
                unset($query[$fullTextField]);
            }
        }

        // For the remaining fields generate a filter query.
        $filter = [];
        foreach ($query as $field => $value) {
            $filter[] = ['term' => [$field => $value]];
        }

        var_dump($fullTextQuery);
        var_dump($filter);

        $result = EsClient::build(true)->search([
            'index' => implode(',', [Indexes::EPF_IDX, Indexes::SPINS_IDX]),
            'size' => self::PAGE_SIZE,
            'from' => $from,
            'body' => [
                // The query body.
                'query' => [
                    // todo: use constant_score, we don't need relevance here
                    'bool' => [
                        'filter' => $filter,
                        'must' => $fullTextQuery,
                    ],
                ],
                //'aggs' => [
                //
                //],
                'sort' => ['song_name.sort'],
            ],
        ]);

        return $result;
    }

    protected function searchArtists(array $query): array
    {
        $result = EsClient::build(true)->search([
            'index' => implode(',', [Indexes::EPF_IDX, Indexes::SPINS_IDX]),
            'body' => [
                // The query body.
                'query' => [
                    'bool' => [
                        'filter' => $this->getSelectedFieldsFilter($query),
                        'match_all' => [],
                    ],
                ],
                'aggs' => [
                    'groupByName' => [
                        'terms' => [
                            'field' => "artist_name.norm",
                            //'order' => ['maxScore' => 'desc'], // todo: order by .sort
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
                ],
                'size' => 0, // don't return search hits, because we work with aggregated buckets only
            ],
        ]);

        return $result;
    }

    protected function searchReleases(array $query): array
    {
        $result = EsClient::build(true)->search([
            'index' => implode(',', [Indexes::EPF_IDX, Indexes::SPINS_IDX]),
            'body' => [
                // The query body.
                'query' => [
                    'bool' => [
                        'filter' => $this->getSelectedFieldsFilter($query),
                        'match_all' => [],
                    ],
                ],
                'aggs' => [
                    'groupByName' => [
                        'terms' => [
                            'field' => "release_title.norm",
                            //'order' => ['maxScore' => 'desc'], // todo: order by .sort
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
                ],
                'size' => 0, // don't return search hits, because we work with aggregated buckets only
            ],
        ]);

        return $result;
    }

    protected function getSelectedFieldsFilter(array $query): array
    {
        $selectedMatch = [];
        foreach ($query as $field => $value) {
            $selectedMatch[] = ['term' => ["$field.norm" => $value]];
        }

        return $selectedMatch;
    }
}
