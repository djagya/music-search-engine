<?php

namespace Search;

class Indexes
{
    const EPF_IDX = 'epf';
    const SPINS_IDX = 'spins';

    public static function init(string $index): void
    {
        echo "Resetting $index index\n";
        echo "Mappings:\n";
        echo json_encode(static::getMappings(), JSON_PRETTY_PRINT) . "\n";

        if ($index === self::EPF_IDX) {
            static::epf();
        } elseif ($index === self::SPINS_IDX) {
            static::spins();
        } else {
            throw new \InvalidArgumentException('Invalid index name');
        }
    }

    protected static function epf(): void
    {
        $client = EsClient::build();
        if ($client->indices()->exists(['index' => static::EPF_IDX])) {
            $client->indices()->delete(['index' => static::EPF_IDX]);
        }
        $result = $client->indices()->create([
            'index' => static::EPF_IDX,
            'body' => [
//                'settings' => [
//                    'number_of_shards' => 3,
//                ],
                'mappings' => static::getMappings(),
            ],
        ]);

        var_dump($result);
    }

    protected static function spins(): void
    {
        $client = EsClient::build();
        if ($client->indices()->exists(['index' => static::SPINS_IDX])) {
            $client->indices()->delete(['index' => static::SPINS_IDX]);
        }
        $result = $client->indices()->create([
            'index' => static::SPINS_IDX,
            'body' => [
//                'settings' => [
//                    'number_of_shards' => 3,
//                ],
                'mappings' => static::getMappings(),
            ],
        ]);

        var_dump($result);
    }

    protected static function getMappings(): array
    {
        // A raw field to filter on when we look for relatedResponse suggestions based on a selected value.
        // todo: does this item requires normalization? what do we do with different cases for example?
        $rawField = ['type' => 'keyword'];

        return [
            'properties' => [
                'artist_name' => [
                    'type' => 'text',
                    'fields' => [
                        'raw' => $rawField,
                    ],
                ],
                'song_name' => [
                    'type' => 'text',
                    'fields' => [
                        'raw' => $rawField,
                    ],
                ],
                'release_title' => [
                    'type' => 'text',
                    'fields' => [
                        'raw' => $rawField,
                    ],
                ],
            ],
        ];
    }
}
