<?php

namespace app\search;

use app\EsClient;
use app\Indexes;
use app\Logger;
use InvalidArgumentException;
use stdClass;

class ChartSearch
{
    const TYPE_SONGS = 'songs';
    const TYPE_ARTISTS = 'artists';
    const TYPE_RELEASES = 'releases';

    protected $type = self::TYPE_SONGS;
    protected $chartMode = false;
    protected $meta = false;
    protected $index;
    protected $logger;
    protected $es;
    // todo: implement date range filter to allow to chart spins
    protected $dateFrom;
    protected $dateTo;
    protected $page;
    protected $pageSize;

    public function __construct(string $type, bool $chartMode, bool $meta = false)
    {
        $this->type = $type;
        $this->chartMode = $chartMode;
        $this->meta = $meta;
        $this->logger = Logger::get('search');
        $this->es = EsClient::build(true);
    }

    /**
     * @param array $query array of fields values to use in filter query
     * @param array $params pagination and other params
     * @return array rows for the current page
     */
    public function search(array $query, array $params = ['page' => 0, 'pageSize' => 25]): array
    {
        $this->index = !empty($params['index']) ? $params['index'] : null;
        $this->page = $params['page'];
        $this->pageSize = $params['pageSize'];

        $result = null;
        if ($this->type === self::TYPE_SONGS) {
            $result = $this->searchSongs($query);
        } elseif ($this->type === self::TYPE_ARTISTS) {
            $result = $this->searchArtists($query);
        } elseif ($this->type === self::TYPE_RELEASES) {
            $result = $this->searchReleases($query);
        } else {
            throw new InvalidArgumentException("Invalid type {$this->type}");
        }

        if ($this->meta) {
            return $result;
        }

        return [
            'took' => $result['took'],
            'total' => $result['hits']['total'],
            'page' => $params['page'],
            'pageSize' => $params['pageSize'],
            'rows' => array_map(function (array $hit) {
                return array_merge(['_id' => $hit['_id']], $hit['_source']);
            }, $result['hits']['hits']),
        ];
    }

    // todo: here only prefix matching is supported
    protected function searchSongs(array $query): array
    {
        $from = $this->page * $this->pageSize;

        // todo: chart mode using the db to be able to group by on the whole dataset.
        //if ($this->chartMode) {
        //    $res = Db::spins()
        //        ->query('SELECT *, count(*) FROM spins.spins_dump group by artist_name, release_title, song_name;')
        //        ->fetchAll();
        //}

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
            if ($value) {
                $filter[] = ['term' => [$field => $value]];
            }
        }

        $params = [
            'index' => $this->getIndexName(),
            'size' => $this->pageSize,
            'from' => $from,
            'body' => [
                'query' => [
                    'bool' => [
                        'filter' => $filter,
                        'must' => $fullTextQuery ?: ['match_all' => new stdClass()],
                    ],
                ],
                'size' => 0,
                //'aggs' => [
                //
                //],
                // todo: sort
                //'sort' => ['song_name.sort'],
            ],
        ];
        if ($this->chartMode) {
            $params['body']['size'] = 0;
            $params['body']['aggs'] = [
                'my_buckets' => [
                    'composite' => [
                        'sources' => [
                            ['song' => ['terms' => ['field' => 'song_name.norm']]],
                            ['artist' => ['terms' => ['field' => 'artist_name.norm']]],
                            ['release' => ['terms' => ['field' => 'release_title.norm']]],
                        ],
                    ],
                    'aggs' => [
                        'topHits' => [
                            'top_hits' => [
                                'size' => 1,
                            ],
                        ],
                        'my_order' => [
                            'bucket_sort' => [
                                'sort' => ["_count" => ['order' => 'desc']],
                            ],
                        ],
                    ],
                ],
            ];
        }

        $this->logParams("Chart [{$this->type}] params", $params);
        $result = $this->es->search($params);
        $this->logger->info("Chart [{$this->type}] took {$result['ms']}ms");

        return $result;
    }

    protected function searchArtists(array $query): array
    {
        $result = $this->es->search([
            'index' => $this->getIndexName(),
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
        $result = $this->es->search([
            'index' => $this->getIndexName(),
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

    protected function getIndexName(): string
    {
        return $this->index ?: implode(',', [Indexes::EPF_IDX, Indexes::SPINS_IDX]);
    }

    protected function logParams(string $message, $params): void
    {
        $this->logger->info($message, ['params' => json_encode($params)]);
    }
}
