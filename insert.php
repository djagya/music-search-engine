<?php

use Elasticsearch\ClientBuilder;

require 'vendor/autoload.php';
include './db.php';

// https://github.com/elastic/elasticsearch-php
$client = ClientBuilder::create()
    ->setHosts(['es01', 'es02'])
    ->build();

$totalDocCount = $pdo->query('select count(id) from spins')->fetchColumn();
echo "Harvesting $totalDocCount documents \n";

// Batch index.
$params = ['body' => []];

$limit = 10000;
$offset = 0;
do {
    $rows = $pdo->prepare('select * from spins limit ? offset ?');
    $rows->execute([$limit, $offset]);

    foreach ($rows->fetchAll() as $row) {
        $params['body'][] = [
            'index' => [
                '_index' => 'spins',
                '_id' => $row['id'],
            ]
        ];

        $params['body'][] = $row;
    }

    $step = $offset . '-' . ($offset + $limit);
    echo "Sending batch $step out of $totalDocCount\n";

    $responses = $client->bulk($params);
    echo "Sent\n";
    // Prepare for a new batch
    $params = ['body' => []];
    // unset the bulk response when you are done to save memory
    unset($responses);

    $offset += $limit;
} while (!empty($rows));
// Send the last batch if it exists
if (!empty($params['body'])) {
    $responses = $client->bulk($params);
}

