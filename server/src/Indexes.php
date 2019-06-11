<?php

namespace Search;

use Search\search\BaseSearch;

class Indexes
{
    const EPF_IDX = 'epf';
    const SPINS_IDX = 'spins';

    public static function init(string $index): void
    {
        if (!in_array($index, [self::EPF_IDX, self::SPINS_IDX])) {
            throw new \InvalidArgumentException("Invalid index name '$index'");
        }

        echo "Resetting $index index\n";
        echo "Settings:\n";
        echo json_encode(static::getSettings(), JSON_PRETTY_PRINT) . "\n";
        echo "Mappings:\n";
        echo json_encode(static::getMappings(), JSON_PRETTY_PRINT) . "\n";

        static::resetIndex($index);
    }

    protected static function resetIndex(string $index): void
    {
        $client = EsClient::build();
        if ($client->indices()->exists(['index' => $index])) {
            $client->indices()->delete(['index' => $index]);
        }
        $result = $client->indices()->create([
            'index' => $index,
            'body' => [
                'settings' => static::getSettings(),
                'mappings' => static::getMappings(),
            ],
        ]);

        var_dump($result);
    }

    // todo: add analyzer that will be applied to the queries we send to ES. should be the same as the normalizer used during the indexing
    protected static function getSettings(): array
    {
        return [
            // 'number_of_shards' => 3, todo: enable in production. check why it's important
            'analysis' => [
                'analyzer' => [
                    // The fuzzy searchable field supporting a wide range of languages ignoring misspelling.
                    'acAnalyzer' => [
                        // todo: support fuziness https://www.elastic.co/guide/en/elasticsearch/guide/current/fuzziness.html
                        'tokenizer' => 'icu_tokenizer',
                        'filter' => ['icu_folding'],
                    ],
                    // To sort keywords in different languages and spellings app using DUCET collation.
                    // Emits keys for efficient sorting.
                    'ducetSort' => [
                        'tokenizer' => 'keyword',
                        'filter' => ['icu_collation'],
                    ],
                ],
                'normalizer' => [
                    // To group found hits.
                    'caseInsensitive' => [
                        'type' => 'custom',
                        'filter' => ['lowercase'],
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
    protected static function getMappings(): array
    {
        $props = [];
        foreach (BaseSearch::AC_FIELDS as $column) {
            $props[$column] = [
                'type' => 'text',
                'analyzer' => 'acAnalyzer',
                'fields' => [
                    'raw' => ['type' => 'keyword'],
                    'norm' => ['type' => 'keyword', 'normalizer' => 'caseInsensitive'],
                    'sort' => ['type' => 'text', 'analyzer' => 'ducetSort'],
                ],
            ];
        }

        return ['properties' => $props];
    }
}
