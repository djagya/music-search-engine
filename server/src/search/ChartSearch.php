<?php

namespace app\search;

use app\EsClient;
use app\Indexes;
use app\Logger;
use InvalidArgumentException;
use Throwable;

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
        $this->direction = strpos($params['sort'] ?? '', '-') === 0 ? 'desc' : 'asc';
        $this->sort = !empty($params['sort']) ? ltrim($params['sort'], '-') : null;
        $this->after = $params['after'] ?? null;

        $result = null;
        if ($this->type === self::TYPE_SONGS) {
            $result = $this->searchSongs($query);
        } elseif ($this->type === self::TYPE_ARTISTS) {
            $result = $this->searchGroup($query, 'artist_name', ['label_name', 'release_genre']);
        } elseif ($this->type === self::TYPE_RELEASES) {
            $result = $this->searchGroup($query, 'release_title',
                ['artist_name' => 'artist_name.norm', 'label_name', 'release_genre', 'release_year_released']);
        } else {
            throw new InvalidArgumentException("Invalid type {$this->type}");
        }

        return array_merge_recursive($result, [
            'pagination' => [
                'page' => $this->page,
                'pageSize' => $this->pageSize,
                'prev' => $this->after,
            ],
        ]);
    }

    protected function getSortField(string $default): string
    {
        if (!$this->sort) {
            return $default;
        }
        if (($this->index === 'spins' && $this->sort === 'spin_timestamp') || $this->sort === 'release_year_released') {
            return $this->sort;
        }

        return "{$this->sort}.sort";
    }

    /**
     * @param array $query
     * @param string $countField get total count of the set based on this field cardinality (# of unique values)
     * @return array
     */
    protected function getParams(array $query, string $countableField): array
    {
        return [
            'index' => $this->getIndexName(),
            'body' => [
                'query' => $this->getQuery($query),
                'aggs' => [
                    'totalCount' => [
                        'cardinality' => ['field' => $countableField],
                    ],
                ],
            ],
        ];
    }

    /**
     * "Songs" grid type. Represents unique rows in the database.
     *
     * One of the things that can be improved here is the chart mode.
     * To implement correct chart mode the integration with the data source DB is required.
     */
    protected function searchSongs(array $query): array
    {
        $sortField = $this->getSortField('song_name.sort');
        $params = array_merge_recursive($this->getParams($query, '_id'), [
            'size' => $this->pageSize,
            'from' => $this->page * $this->pageSize,
            'body' => [
                'sort' => [$sortField => $this->direction],
            ],
        ]);

        $this->logParams("Chart [{$this->type}] params", $params);
        $result = $this->es->search($params);
        $this->logger->info("Chart [{$this->type}] took {$result['took']}ms");

        $result['query'] = $params['body'];
        if ($this->debug) {
            return array_merge(['query' => $params], $result);
        }

        $sort = implode('',
            [$this->direction === 'desc' ? '-' : '', str_replace('.sort', '', $sortField)]);

        return [
            'took' => $result['took'],
            'total' => ['value' => $result['aggregations']['totalCount']['value'], 'relation' => ''],
            'rows' => array_map(function (array $hit) {
                return array_merge(['_id' => $hit['_id']], $hit['_source']);
            }, $result['hits']['hits']),
            'pagination' => [
                'sort' => $sort,
            ],
        ];
    }

    /**
     * Select a dataset grouped by the specified attribute (only fields with a sub-field .norm are allowed).
     * Collect distinct values of specified fields within each group (similar to GROUP_CONCAT in SQL).
     * @param array $query
     * @param string $groupBy a field with a '.norm' subfield
     * @param array $collect list of field names to collect within each group. Also supported [destField => sourceField]
     * @return array result
     */
    protected function searchGroup(array $query, string $groupBy, array $collect): array
    {
        $params = $this->getGroupedGridQuery($groupBy, $query, $collect);

        $this->logParams("Chart [{$this->type}] params", $params);
        $result = $this->es->search($params);
        $this->logger->info("Chart [{$this->type}] took {$result['took']}ms");

        $namesAgg = $result['aggregations']['names'];
        // If pagination loads previous pages, take only last, current page.
        $buckets = $this->sort === 'count' ? array_slice($namesAgg['buckets'], -$this->pageSize) : $namesAgg['buckets'];

        // Prepare to be consumed by the chart web-app.
        $rows = array_map(function (array $item) use ($collect) {
            $hit = $item['topHits']['hits']['hits'][0];

            $collected = [];
            foreach ($collect as $k => $f) {
                if (is_int($k)) {
                    $k = $f;
                }
                $collected[$k] = array_map('ucwords', array_column($item[$f]['buckets'], 'key'));
            }

            // Take some document meta information, source, and collected groups of distinct values.
            return array_merge(
                ['_id' => $hit['_id'], 'count' => $item['doc_count']],
                $hit['_source'],
                $collected
            );
        }, $buckets);

        if ($this->debug) {
            return array_merge(['query' => $params], $result);
        }

        $sort = implode('', [$this->direction === 'desc' ? '-' : '', $this->sort ?: $groupBy]);

        return [
            'took' => $result['took'],
            'total' => ['value' => $result['aggregations']['totalCount']['value'], 'relation' => ''],
            'pagination' => [
                'after' => $namesAgg['after_key'][$groupBy] ?? null,
                'sort' => $sort,
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Return a grouping aggregation that will select distinct $field values in two possible ways:
     * 1) Select top N groups, can be paginated but requires to load all previous pages. Used with "count" order.
     * 2) Select composite buckets, can be paginated using an "after" cursor, memory-efficient.
     */
    protected function getGroupAggregation(string $field): array
    {
        if ($this->sort === 'count') {
            // Take top grouped results, can be paginated only by loading all previous results, so pagination should be limited.
            return [
                'terms' => [
                    'size' => $this->pageSize + $this->pageSize * $this->page,
                    'field' => "$field.norm",
                    'order' => ['_count' => $this->direction],
                ],
            ];
        }

        // Composite grouping allows to paginate the whole dataset, with usage of "after" cursor.
        return [
            'composite' => array_filter([
                'size' => $this->pageSize,
                'sources' => [
                    [$field => ['terms' => ['field' => "$field.norm", 'order' => $this->direction]]],
                ],
                'after' => $this->after ? [$field => $this->after] : null,
            ]),
        ];
    }

    protected function getGroupedGridQuery(string $field, array $query, array $collect): array
    {
        $collectAggs = [];
        foreach ($collect as $f) {
            // Select distinct names of a group.
            $collectAggs[$f] = ['terms' => ['size' => 1000, 'field' => $f]];
        }

        return array_merge_recursive($this->getParams($query, "$field.norm"), [
            'body' => [
                'aggs' => [
                    'names' => array_merge($this->getGroupAggregation($field), [
                        'aggs' => array_merge([
                            // Return the top document to get a display value from its field.
                            'topHits' => ['top_hits' => ['size' => 1]],
                        ], $collectAggs),
                    ]),
                ],
                'size' => 0, // don't return search hits, because we work with aggregated buckets only
            ],
        ]);
    }

    protected function getQuery(array $query): array
    {
        // Generate query for fields with supported prefix search (main AC fields).
        // todo: write in bachelor. elasticsearch doesn't store positions of terms (unless it's enabled as term_vector),
        // so can't use edge_ngrams for prefix matching as it doesn't fetch values STARTING with the specified filter.
        $fullTextQuery = [];
        foreach (BaseSearch::AC_FIELDS as $fullTextField) {
            if (!empty($query[$fullTextField])) {
                $fullTextQuery[] = ['match' => ["$fullTextField" => $query[$fullTextField]]];
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
                [$from, $to] = array_filter(array_pad(explode('-', $value), 2, null), 'is_numeric');
                if (!$to) {
                    // Invalid values.
                    if (!$from) {
                        continue;
                    }
                    $to = $from;
                }
                $filter[] = ['range' => [$field => ['gte' => $from, 'lte' => $to]]];
            } elseif ($field === 'spin_timestamp') {
                $f = [];
                try {
                    if (!empty($value['from'])) {
                        $f['gte'] = gmdate('Y-m-d H:i:s', strtotime($value['from']));
                    }
                    if (!empty($value['to'])) {
                        $f['lte'] = gmdate('Y-m-d H:i:s', strtotime($value['to']));
                    }
                } catch (Throwable $e) {
                    // Catch errors when input string can't be converted to time.
                }
                if ($f) {
                    $filter[] = ['range' => [$field => $f]];
                }
            } else {
                $filter[] = ['prefix' => [$field => $value]];
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
