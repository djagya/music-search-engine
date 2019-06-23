<?php

use app\search\TypingSearch;

require 'vendor/autoload.php';

$started = time();
//echo date('Y-m-d H:i:s');
$lastExecuted = null;
$lastTimestamp = null;

//$delay = 0.1;
$delay = 0; // to check the throughput

$searchModels = [
    'artist_name' => new TypingSearch('artist_name', [], false),
    'release_title' => new TypingSearch('release_title', [], false),
    'song_name' => new TypingSearch('artist_name', [], false),
];
$fields = array_keys($searchModels);

$id = uniqid('', true);
$resLog = fopen(__DIR__ . "/../logs/perf_data_$id.log", 'w+');

$perfLog = fopen(__DIR__ . '/../data/perf.log', 'r');
$count = 0;

echo "Searching with $delay delay, id $id\n";

while (($line = fgets($perfLog)) !== false) {
    [$date, $time, $body] = explode(' ', $line, 3);

    $body = json_decode($body, true);
    $origTime = $body['t'];
    $query = $body['q'];

    $field = array_rand($fields);
    $result = $searchModels[$field]->search($query);

    $data = [
        'datetime' => date('Y-m-d H:i:s'),
        'took' => $result['took'],
        'count' => $result['total']['value'],
        'origTook' => $origTime,
        'origCount' => $body['n'],
        'field' => $field,
    ];
    fputcsv($resLog, $data);

    $lastExecuted = time();
    $count += 1;

    if ($count % 100 === 0) {
        echo sprintf("%s total: %d, last: took %d ms, count %d, query '%s' \n", date('Y-m-d H:i:s'), $count,
            $data['took'], $data['count'], $query);
    }
    if ($delay) {
        usleep($delay * 1000000);
    }
}
fclose($perfLog);
fclose($resLog);

echo "Finished $id\n";
