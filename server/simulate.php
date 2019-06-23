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

$resLog = fopen(__DIR__ . '/../logs/perf_data.log', 'w+');

$perfLog = fopen(__DIR__ . '/../data/perf.log', 'r');
$count = 0;

echo "Searching with $delay delay\n";

while (($line = fgets($perfLog)) !== false) {
    [$date, $time, $body] = explode(' ', $line, 3);

    //$timestamp = strtotime("$date $time");
    //if (!$lastTimestamp) {
    //    $lastTimestamp = $timestamp;
    //    $lastExecuted = time();
    //}

    //$origDelay = $timestamp - $lastTimestamp;
    //$currentDelay = time() - $lastExecuted;
    //// Wait the same amount of time as originally before sending the query.
    //if ($origDelay >= $currentDelay) {
    //    sleep($origDelay - $currentDelay);
    //}

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
    usleep($delay * 1000000);
}
echo "Finished\n";
fclose($perfLog);
fclose($resLog);
