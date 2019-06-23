<?php

use app\search\TypingSearch;

require 'vendor/autoload.php';

$forks = min($argv[1] ?? 1, 20);
$delay = max($argv[2] ?? 0, 0);
$feedFile = realpath(__DIR__ . '/../data/perf.log');

$path = __DIR__ . '/../logs/perf';
$logsDir = realpath($path);

if (($argv[1] ?? '') === 'help') {
    echo "Usage:\n\n";
    echo "php server/simulate.php [forks] [delay]\n\n";
    echo "Where\n";
    echo "[forks] determines the max number of simultaneous search requests. Default: 1\n";
    echo "[delay] specifies a base delay (further randomized) between queries. Default: 0\n";
    exit();
}
// Create result logs dir.
if (!$logsDir) {
    echo "Creating \t $path\n\n";
    mkdir($path);
    $logsDir = realpath($path);
}

$id = uniqid();

echo "Running forks=$forks delay=$delay id=$id\n";
echo "Source file \t $feedFile\n";
echo "Logs dir \t $logsDir\n\n";

// Load the whole dump to randomize it for each fork.
$lines = explode("\n", file_get_contents($feedFile));
echo sprintf("Total queries \t %d \n", count($lines));

$searchModels = [
    'artist_name' => new TypingSearch('artist_name', [], false),
    'release_title' => new TypingSearch('release_title', [], false),
    'song_name' => new TypingSearch('artist_name', [], false),
];

// Spawn forks if needed.
$forkId = '_0';
if ($forks > 1) {
    $pids = [];
    for ($i = 1; $i < $forks; $i++) {
        $pids[$i] = pcntl_fork();
        if (!$pids[$i]) {
            $forkId = "_$i";
            break;
        }
    }
}
$id .= $forkId;

// Randomize lines.
shuffle($lines);

$resLog = fopen("$logsDir/$id.log", 'w+');
$count = 0;
while ($line = array_pop($lines)) {
    [$date, $time, $body] = explode(' ', $line, 3);

    $body = json_decode($body, true);
    $origTime = $body['t'];
    $query = $body['q'];

    $field = array_rand($searchModels);
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

    $count += 1;

    if ($count % 1000 === 0) {
        echo sprintf("%s %s %d\tlast %dms\t %d\t'%s'\n", date('Y-m-d H:i:s'), $id, $count,
            $data['took'], $data['count'], substr($query, 0, 10));
    }
    if ($delay) {
        usleep($delay * 1000000);
    }
}
fclose($resLog);

echo "Finished $id\n";
