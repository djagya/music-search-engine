<?php

use Elasticsearch\ClientBuilder;

require 'vendor/autoload.php';
include './db.php';

$client = ClientBuilder::create()->build();

$totalDocCount = $pdo->prepare('select count(id) from spins')->fetchColumn();
echo "Harvesting $totalDocCount documents \n";

// Batch index.
$params = ['body' => []];

$limit = 10000;
$offset = 0;
do {
    $rows = $pdo->prepare('select * from spins limit ? offset ?')->fetchAll(PDO::FETCH_ASSOC, [$limit, $offset]);
    foreach ($rows as $row) {
        $params['body'][] = [
            'index' => [
                '_index' => 'spins',
                '_id' => $row['id'],
            ]
        ];

        $params['body'][] = $row;

        // Every 1000 documents stop and send the bulk request
        if ((count($params['body']) * 2) % 10000 == 0) {
            echo "Sending batch\n";

            $responses = $client->bulk($params);
            // erase the old bulk request
            $params = ['body' => []];
            // unset the bulk response when you are done to save memory
            unset($responses);
        }
    }

    break;

    $offset += $limit;
} while (!empty($rows));
// Send the last batch if it exists
if (!empty($params['body'])) {
    $responses = $client->bulk($params);
}

