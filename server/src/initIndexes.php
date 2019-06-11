<?php

# todo: POST (or PUT?) /index to create all required indexes with correct settings, mappings for needed fields
# must be done before ingesting them so call it in init.sh before data load
# In development mode use only one shard and no replicas.

use Search\EsClient;

$client = EsClient::build();

// A raw field is needed to group by the exact property value.
$rawField = ['type' => 'string', 'index' => 'not_analyzed'];

$result = $client->indices()->create([
    'index' => 'spins',
    'body' => [

    ],
]);

var_dump($result);

$result = $client->indices()->putMapping([
    'index' => 'spins',
    'body' => [
        'properties' => [
            'artist_name' => [
                'type' => 'string',
                'fields' => [
                    'raw' => $rawField,
                ],
            ],
            'song_name' => [
                'type' => 'string',
                'fields' => [
                    'raw' => $rawField,
                ],
            ],
            'release_title' => [
                'type' => 'string',
                'fields' => [
                    'raw' => $rawField,
                ],
            ],
// ignore composer name for simplicity.
//            'song_composer' => [
//                'type' => 'string',
//                'fields' => [
//                    'raw' => $rawField,
//                ],
//            ],
        ],
    ],
]);

var_dump($result);
