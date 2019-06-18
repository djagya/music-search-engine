<?php

namespace app;

use app\search\BaseSearch;
use InvalidArgumentException;

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

    // todo: add analyzer that will be applied to the queries we send to ES. should be the same as the normalizer used during the indexing
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
     * There are few fields for different purposes.
     *
     * SEARCH RESULTS
     * - the root `{column}` is a normalized and tokenized full-text searchable field.
     *      Used to fetch autocomplete suggestions regardless of the terms case, character set, language.
     *      Supports misspelled terms search.
     *      Values are transformed to the unicode normalization form.
     *
     * DISPLAY RESULTS
     * - `{column}.raw` "keyword" field contains the exact original value.
     *      Used as a display value as we want to preserve the original spelling, language.
     *
     * GROUP RESULTS AND FILTER RESULTS
     * - `{column}.norm` "keyword" field contains a value in lowercase.
     *      Used as a key for aggregation of the matched hits.
     *      Allow to differentiate between differently spelled names, as they usually represent different
     *      artists/songs/releases.
     */
    protected static function getMappings(string $index): array
    {
        $props = [
            'label_name' => [
                'type' => 'keyword',
                'fields' => [
                    'norm' => ['type' => 'keyword', 'normalizer' => 'caseInsensitive'],
                ],
            ],
            'release_genre' => [
                'type' => 'keyword',
                'fields' => [
                    'norm' => ['type' => 'keyword', 'normalizer' => 'caseInsensitive'],
                ],
            ],
            'release_year_released' => [
                'type' => 'short',
                'ignore_malformed' => true,
            ],
        ];
        if ($index === self::SPINS_IDX) {
            $props['spin_timestamp'] = [
                'type' => 'date',
                'format' => 'yyyy-MM-dd HH:mm:ss',
            ];
        }

        foreach (BaseSearch::AC_FIELDS as $column) {
            $props[$column] = [
                'type' => 'text',
                'analyzer' => 'acAnalyzer',
                'search_analyzer' => 'acQueryAnalyzer',
                'fields' => [
                    'raw' => ['type' => 'keyword'],
                    'norm' => ['type' => 'keyword', 'normalizer' => 'caseInsensitive'],
                    // To sort keywords in different languages and spellings app using DUCET collation.
                    // Emits keys for efficient sorting.
                    'sort' => ['type' => 'icu_collation_keyword', 'index' => false],
                ],
            ];
        }

        return ['properties' => $props];
    }
}
