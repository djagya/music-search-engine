<?php

namespace app;

use app\search\BaseSearch;
use InvalidArgumentException;

/**
 * Contains indexes and fields declarations and some utility code to reset and apply the settings.
 */
class Indexes
{
    const EPF_IDX = 'epf';
    const SPINS_IDX = 'spins';

    protected $index;
    protected $reset = false;

    public function __construct(?string $index, bool $reset = false)
    {
        $this->index = $index;
        $this->reset = $reset;
        Logger::get()->pushProcessor(function ($entry) {
            echo $entry['message'] . "\n";

            return $entry;
        });
    }

    /**
     * @param string|null $index when null apply to all indexes
     * @param bool $reset
     * @return array
     */
    public static function init(?string $index, bool $reset = false): array
    {
        $both = [self::EPF_IDX, self::SPINS_IDX];
        if ($index && !in_array($index, $both)) {
            throw new InvalidArgumentException("Invalid index name '$index'");
        }
        $result = [];
        foreach (([$index] ?: $both) as $idx) {
            $result[$idx] = (new static($idx, $reset))->apply();
        }

        return $result;
    }

    public function apply(): array
    {
        // Apply reset, apply settings and mappings
        $endpoint = EsClient::build()->indices();
        $index = ['index' => $this->index];
        $exists = $endpoint->exists($index);

        if ($this->reset) {
            if ($exists) {
                Logger::get()->info("'$this->index' index delete");
                $endpoint->delete($index);
            }
            Logger::get()->info("'$this->index' index create");

            return $endpoint->create($index + [
                    'body' => [
                        'settings' => [
                                'number_of_shards' => getenv('ENV') === 'production' ? 3 : 1,
                            ] + static::getSettings(),
                        'mappings' => static::getMappings($this->index),
                    ],
                ]);
        }

        // Update settings. Requires close/open.
        Logger::get()->info("'$this->index' index settings update");
        $endpoint->close($index);
        $result = $endpoint->putSettings($index + ['body' => ['settings' => self::getSettings()]]);
        $endpoint->open($index);

        return $result;
    }

    protected static function getSettings(): array
    {
        return [
            'analysis' => [
                'analyzer' => [
                    'acAnalyzer' => [
                        'tokenizer' => 'icu_tokenizer',
                        'filter' => ['icu_folding', 'acFilter'],
                    ],
                    'acQueryAnalyzer' => [
                        'tokenizer' => 'icu_tokenizer',
                        'filter' => ['icu_folding'],
                    ],
                ],
                'normalizer' => [
                    // To group found hits.
                    'caseInsensitive' => [
                        'type' => 'custom',
                        'filter' => ['lowercase'],
                    ],
                ],
                'filter' => [
                    'acFilter' => [
                        'type' => 'edge_ngram',
                        'min_gram' => 1,
                        'max_gram' => 20,
                    ],
                ],
            ],
        ];
    }

    /**
     * Return the mappings config for an index.
     * All fields are specified explicitly and configured to reduce disk usage.
     *
     * When field.doc_values = true, it can be used in aggregations and sorting.
     * When field.index = true, it becomes searchable and can be used in queries.
     *
     * Sub-fields "field.norm" are used for grouping and searching.
     */
    protected static function getMappings(string $index): array
    {
        $props = [
            'song_duration' => [
                'type' => 'short',
                'index' => false,
                'doc_values' => false,
                // Convert strings to numbers, truncate fractions.
                'coerce' => true,
            ],
            'song_isrc' => [
                // ISRC can be searched and aggregated.
                'type' => 'keyword',
                'index' => true,
                'doc_values' => true,
            ],
            'release_genre' => [
                // Genre name can be searched and aggregated by its normalized value.
                'type' => 'keyword',
                'normalizer' => 'caseInsensitive',
                'index' => true,
                'doc_values' => true,
            ],
            'release_various_artists' => [
                'type' => 'boolean',
                'index' => false,
                'doc_values' => false,
            ],
            'release_medium' => [
                'type' => 'keyword',
                'index' => false,
                'doc_values' => false,
            ],
            'release_upc' => [
                // UPC can be searched and aggregated.
                'type' => 'keyword',
                'index' => true,
                'doc_values' => true,
            ],
            'cover_art_url' => [
                'type' => 'keyword',
                'index' => false,
                'doc_values' => false,
            ],
            'release_year_released' => [
                // Release year can be searched and aggregated.
                'type' => 'short',
                'index' => true,
                'doc_values' => true,
                'ignore_malformed' => true,
            ],
            'label_name' => [
                // Label name can be searched and aggregated by its normalized value.
                'type' => 'keyword',
                'normalizer' => 'caseInsensitive',
                'index' => true,
                'doc_values' => true,
            ],
        ];

        // Id fields don't need to be searched or aggregated.
        $idType = ['type' => 'long', 'index' => false, 'doc_values' => false];
        if ($index === self::EPF_IDX) {
            $indexProps = [
                'song_id' => $idType,
                'artist_id' => $idType,
                'collection_id' => $idType,
            ];
        } elseif ($index === self::SPINS_IDX) {
            $indexProps = [
                'id' => $idType,
                'spin_timestamp' => [
                    // Spin timestamp can be searched and aggregated.
                    'type' => 'date',
                    'format' => 'yyyy-MM-dd HH:mm:ss',
                    'index' => true,
                    'doc_values' => true,
                ],
            ];
        }
        $props = array_merge($props, $indexProps ?? []);

        // Autocomplete props.
        foreach (BaseSearch::AC_FIELDS as $column) {
            $props[$column] = [
                'type' => 'text',
                'analyzer' => 'acAnalyzer',
                'search_analyzer' => 'acQueryAnalyzer',
                'index' => true,
                'doc_values' => false,
                'fields' => [
                    // To group and filter by, ignores case, but preserves spelling.
                    'norm' => [
                        'type' => 'keyword',
                        'normalizer' => 'caseInsensitive',
                        'index' => true,
                        'doc_values' => true,
                        // Improve execution time for 'term' aggregations based on this field by preloading global ordinals - unique numbering for all terms.
                        // It makes sense here, as .norm sub-field is used almost in every query to group results by.
                        // https://www.elastic.co/guide/en/elasticsearch/reference/current/eager-global-ordinals.html
                        'eager_global_ordinals' => true,
                    ],
                    // To sort keywords in different languages and spellings app using DUCET collation.
                    'sort' => ['type' => 'icu_collation_keyword', 'index' => false, 'doc_values' => true],
                ],
            ];
        }

        return ['properties' => $props];
    }
}
