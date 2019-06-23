<?php

namespace app\search;

use app\EsClient;
use app\Indexes;
use app\Logger;
use InvalidArgumentException;

class ChartSearch
{
    const TYPE_SONGS = 'songs';
    const TYPE_ARTISTS = 'artists';
    const TYPE_RELEASES = 'releases';

    protected $type = self::TYPE_SONGS;
    protected $chartMode = false;
    protected $debug = false;
    protected $sort = null;
    protected $direction = null;
    protected $index;
    protected $logger;
    protected $es;
    // todo: implement date range filter to allow to chart spins
    protected $dateFrom;
    protected $dateTo;
    protected $page;
    protected $after;
    protected $pageSize;

    public function __construct(string $type, bool $chartMode, bool $debug = false)
    {
        $this->type = $type;
        $this->chartMode = $chartMode;
        $this->debug = $debug;
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
        $this->page = (int) $params['page'];
        $this->pageSize = (int) $params['pageSize'];
        $this->direction = strpos($params['sort'] ?? '', '-') === 0 ? SORT_DESC : SORT_ASC;
        $this->sort = !empty($params['sort']) ? ltrim($params['sort'], '-') : null;
        $this->after = $params['after'] ?? null;

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

        return array_merge_recursive($result, [
            'pagination' => [
                'page' => $this->page,
                'pageSize' => $this->pageSize,
            ],
        ]);
    }

    protected function searchSongs(array $query): array
    {
        // todo: chart mode using the db to be able to group by on the whole dataset.
        $sortField = $this->index === 'spins' && $this->sort === 'spin_timestamp' ? 'spin_timestamp' : 'song_name.sort';
        $params = [
            'index' => $this->getIndexName(),
            'size' => $this->pageSize,
            'from' => $this->page * $this->pageSize,
            'body' => [
                'query' => $this->getQuery($query),
                'sort' => [$sortField => $this->direction === SORT_DESC ? 'desc' : 'asc'],
                'aggs' => [
                    'totalCount' => [
                        'cardinality' => ['field' => $this->index === 'spins' ? 'id' : 'song_id'],
                    ],
                ],
            ],
        ];

        $this->logParams("Chart [{$this->type}] params", $params);
        $result = $this->es->search($params);
        $this->logger->info("Chart [{$this->type}] took {$result['took']}ms");

        $result['query'] = $params['body'];

        if ($this->debug) {
            return array_merge(['query' => $params], $result);
        }

        return [
            'took' => $result['took'],
            'total' => ['value' => $result['aggregations']['totalCount']['value'], 'relation' => ''],
            'rows' => array_map(function (array $hit) {
                return array_merge(['_id' => $hit['_id']], $hit['_source']);
            }, $result['hits']['hits']),
            'pagination' => [
                'sort' => implode('',
                    [$this->direction === SORT_DESC ? '-' : '', str_replace('.sort', '', $sortField)]),
            ],
        ];
    }

    protected function searchArtists(array $query): array
    {
        $field = 'artist_name';

        if ($this->sort === 'count') {
            // Take top artists, size is big to fetch the current page of data, no way to paginate.
            $groupAgg = [
                'terms' => [
                    'size' => $this->pageSize + $this->pageSize * $this->page,
                    'field' => "$field.norm",
                    'order' => ['_count' => $this->direction === SORT_ASC ? 'asc' : 'desc'],
                ],
            ];
        } else {
            $groupAgg = [
                'composite' => [
                    'size' => $this->pageSize,
                    'sources' => [
                        [
                            'artist' => [
                                'terms' => [
                                    'field' => "$field.norm",
                                    'order' => $this->direction === SORT_DESC ? 'desc' : 'asc',
                                ],
                            ],
                        ],
                    ],
                ],
            ];
            if ($this->after) {
                $groupAgg['composite']['after'] = ['artist' => $this->after];
            }
        }

        // By default sorted by count.
        $params = [
            'index' => $this->getIndexName(),
            'body' => [
                'query' => $this->getQuery($query),
                'aggs' => [
                    'names' => array_merge($groupAgg, [
                        'aggs' => [
                            // Return the top document to get a display value from its field.
                            'topHits' => [
                                'top_hits' => [
                                    'size' => 1,
                                ],
                            ],
                            'labels' => [
                                'terms' => [
                                    'size' => 1000,
                                    'field' => 'label_name',
                                ],
                            ],
                            'genres' => [
                                'terms' => [
                                    'size' => 1000,
                                    'field' => 'release_genre',
                                ],
                            ],
                        ],
                    ]),
                    'totalCount' => [
                        'cardinality' => ['field' => "$field.norm"],
                    ],
                ],
                'size' => 0, // don't return search hits, because we work with aggregated buckets only
            ],
        ];

        $this->logParams("Chart [{$this->type}] params", $params);
        $result = $this->es->search($params);
        $this->logger->info("Chart [{$this->type}] took {$result['took']}ms");

        $namesAgg = $result['aggregations']['names'];
        $buckets = $this->sort === 'count' ? array_slice($namesAgg['buckets'], -$this->pageSize) : $namesAgg['buckets'];
        $rows = array_map(function (array $item) {
            $hit = $item['topHits']['hits']['hits'][0];

            return array_merge([
                '_id' => $hit['_id'],
                'count' => $item['doc_count'],
            ], $hit['_source'], [
                // todo: for now skip getting real value using top_hits and just format normalized value as Label Name
                'label_name' => array_map('ucwords', array_column($item['labels']['buckets'], 'key')),
                'release_genre' => array_map('ucwords', array_column($item['genres']['buckets'], 'key')),
            ]);
        }, $buckets);

        if ($this->debug) {
            return array_merge(['query' => $params], $result);
        }

        return [
            'took' => $result['took'],
            'total' => ['value' => $result['aggregations']['totalCount']['value'], 'relation' => ''],
            'pagination' => [
                // Needed when not sorted by doc_count.
                'after' => $namesAgg['after_key']['artist'] ?? null,
                'prev' => $this->after,
                'sort' => implode('',
                    [$this->direction === SORT_DESC ? '-' : '', $this->sort ?: $field]),
            ],
            'rows' => $rows,
        ];
    }

    protected function searchReleases(array $query): array
    {
        $field = 'release_title';

        if ($this->sort === 'count') {
            // Take top artists, size is big to fetch the current page of data, no way to paginate.
            $groupAgg = [
                'terms' => [
                    'size' => $this->pageSize * $this->page,
                    'field' => "$field.norm",
                ],
            ];
        } else {
            $groupAgg = [
                'composite' => [
                    'size' => $this->pageSize,
                    'sources' => [
                        ['release' => ['terms' => ['field' => "$field.norm", 'order' => 'asc']]],
                    ],
                ],
            ];
            if ($this->after) {
                $groupAgg['composite']['after'] = ['release' => $this->after];
            }
        }

        // By default sorted by count.
        $params = [
            'index' => $this->getIndexName(),
            'body' => [
                'query' => $this->getQuery($query),
                'aggs' => [
                    'names' => array_merge($groupAgg, [
                        'aggs' => [
                            // Return the top document to get a display value from its field.
                            'topHits' => [
                                'top_hits' => [
                                    'size' => 1,
                                ],
                            ],
                            'artists' => [
                                'terms' => [
                                    'size' => 1000,
                                    'field' => 'artist_name.norm',
                                ],
                            ],
                            'labels' => [
                                'terms' => [
                                    'size' => 1000,
                                    'field' => 'label_name',
                                ],
                            ],
                            'genres' => [
                                'terms' => [
                                    'size' => 1000,
                                    'field' => 'release_genre',
                                ],
                            ],
                            'released' => [
                                'terms' => [
                                    'size' => 1000,
                                    'field' => 'release_year_released',
                                ],
                            ],
                        ],
                    ]),
                    'totalCount' => [
                        'cardinality' => ['field' => "$field.norm"],
                    ],
                ],
                'size' => 0, // don't return search hits, because we work with aggregated buckets only
            ],
        ];

        $this->logParams("Chart [{$this->type}] params", $params);
        $result = $this->es->search($params);
        $this->logger->info("Chart [{$this->type}] took {$result['took']}ms");

        $namesAgg = $result['aggregations']['names'];
        $buckets = $this->sort === 'count' ? array_slice($namesAgg['buckets'], -$this->pageSize) : $namesAgg['buckets'];
        $rows = array_map(function (array $item) {
            $hit = $item['topHits']['hits']['hits'][0];

            return array_merge([
                '_id' => $hit['_id'],
                'count' => $item['doc_count'],
            ], $hit['_source'], [
                // todo: for now skip getting real value using top_hits and just format normalized value as Label Name
                'artist_name' => array_map('ucwords', array_column($item['artists']['buckets'], 'key')),
                'label_name' => array_map('ucwords', array_column($item['labels']['buckets'], 'key')),
                'release_genre' => array_map('ucwords', array_column($item['genres']['buckets'], 'key')),
                'release_year_released' => array_column($item['released']['buckets'], 'key'),
            ]);
        }, $buckets);

        if ($this->debug) {
            return array_merge(['query' => $params], $result);
        }

        return [
            'took' => $result['took'],
            'total' => ['value' => $result['aggregations']['totalCount']['value'], 'relation' => ''],
            'pagination' => [
                // Needed when not sorted by doc_count.
                'after' => $namesAgg['after_key']['artist'] ?? null,
                'prev' => $this->after,
            ],
            'rows' => $rows,
        ];
    }

    protected function getQuery(array $query): array
    {
        // Generate query for fields with supported prefix search (main AC fields). Use root indexed field.
        // todo: write in bachelor. elasticsearch doesn't store positions of terms (unless it's enabled as term_vector),
        // so can't use edge_ngrams for prefix matching as it doesn't fetch values STARTING with the specified filter.
        $fullTextQuery = [];
        foreach (BaseSearch::AC_FIELDS as $fullTextField) {
            if (!empty($query[$fullTextField])) {
                $fullTextQuery[] = ['prefix' => ["$fullTextField.norm" => $query[$fullTextField]]];
                unset($query[$fullTextField]);
            }
        }

        // For the remaining fields generate a filter query.
        $filter = [];
        foreach ($query as $field => $value) {
            if (!$value) {
                continue;
            }
            if ($field === 'release_year_released') {
                // Filter by year range [from]-[to] or just [year] if single number is specified.
                [$from, $to] = array_pad(explode('-', $value), 2, null);
                if (!$to) {
                    $to = $from;
                }
                $filter[] = ['range' => [$field => ['gte' => $from, 'lte' => $to]]];
            } else {
                $filter[] = ['term' => ["$field.norm" => $value]];
            }
        }

        return [
            'constant_score' => [
                'filter' => [
                    'bool' => [
                        'must' => array_merge($filter, $fullTextQuery),
                    ],
                ],
            ],
        ];
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
