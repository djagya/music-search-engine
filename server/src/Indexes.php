<?php

namespace Search;

class Indexes
{
    public static function createSpins()
    {
        $client = EsClient::build();

        if ($client->indices()->exists(['index' => 'spins'])) {
            $client->indices()->delete(['index' => 'spins']);
        }

        // A raw field to filter on when we look for relatedResponse suggestions based on a selected value.
        // todo: does this item requires normalization? what do we do with different cases for example?
        $rawField = ['type' => 'keyword'];
        $result = $client->indices()->create([
            'index' => 'spins',
            'body' => [
                // todo: enable in production
//                'settings' => [
//                    'number_of_shards' => 3,
//                    'number_of_replicas' => 2
//                ],
                'mappings' => [
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
                        'song_composer' => [
                            'type' => 'text',
                            'fields' => [
                                'raw' => $rawField,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        var_dump($result);
    }
}
