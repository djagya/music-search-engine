<?php

use Elasticsearch\ClientBuilder;

require 'vendor/autoload.php';
include './db.php';

$client = ClientBuilder::create()
    ->setHosts(['es01', 'es02'])
    ->build();

// Temporary make the index more performance for insert.
$client->indices()->putSettings([
    'index' => 'spins',
    'body' => [
        'refresh_interval' => -1,
        'number_of_replicas' => 0,
    ]
]);

$maxIdResponse = $client->search([
    'index' => 'spins',
    'body' => [
        'size' => 0,
        'aggs' => [
            'max_id' => ['max' => ['field' => 'id']],
        ],
    ],
]);
$maxId = $maxIdResponse['aggregations']['max_id']['value'] ?? 0;
$totalDocCount = getPdo()->query("select count(id) from spins where id > $maxId")->fetchColumn();
echo "Total " . format($totalDocCount) . " documents, starting from id $maxId \n";

// Fork multiple processes to make concurrent API requests
$pid = pcntl_fork();
$pids = [];
$chCount = 3;
for ($i = 0; $i < $chCount; $i++) {
    $pids[] = pcntl_fork();

    if (!$pids[$i]) {
        // Insertion
        // Here runs the children.
        harvest($maxId, $i, $chCount);
    }
}


// Wait for finish
for ($i = 0; $i < 3; $i++) {
    pcntl_waitpid($pids[$i], $status, WUNTRACED);
}


// Change the settings back.
$client->indices()->putSettings([
    'index' => 'spins',
    'body' => [
        'refresh_interval' => null,
        'number_of_replicas' => 1,
    ]
]);
$client->indices()->forceMerge(['index' => 'spins']);


function format(int $v)
{
    return number_format($v, 0, ',', '.');
}

function harvest(int $maxId, int $childrenN, int $chCount)
{
    $limit = 20000;
    $fullOffset = $limit * $chCount;
    $offset = 0 + $limit * $childrenN;

    echo "Harvester#$childrenN started, offset - " . format($offset) . ", limit - " . format($limit) . "\n";

    // https://github.com/elastic/elasticsearch-php
    $client = ClientBuilder::create()
        ->setHosts(['es01', 'es02'])
        ->build();
    $pdo = getPdo();
    // Batch index.
    $params = [
        'index' => 'spins',
        'body' => []
    ];

    do {
        $rows = $pdo->prepare("select * from spins where id > $maxId limit ? offset ?");
        $rows->execute([$limit, $offset]);

        foreach ($rows->fetchAll() as $row) {
            $params['body'][] = [
                'index' => [
                    '_index' => 'spins',
                    // _id is auto generated
                ]
            ];

            $params['body'][] = $row;
        }
        if (!$params['body']) {
            echo "Harvester#$childrenN finished\n";
            break;
        }

        $step = format($offset) . ' - ' . format($offset + $limit);
        echo "Harvester#$childrenN batch $step \n";

        $client->bulk($params);
        // Prepare for a new batch
        $params = ['body' => []];

        $offset += $fullOffset;
    } while (!empty($rows));
// Send the last batch if it exists
    if (!empty($params['body'])) {
        $client->bulk($params);
    }
}
