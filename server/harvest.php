<?php

use app\harvest\EpfHarvester;
use app\harvest\SpinsHarvester;
use app\Indexes;

require 'vendor/autoload.php';

// todo: warm cache command to run from performance file

$index = $argv[1] ?? null;
$count = $argv[2] ?? 1;
$reset = !!array_filter($argv, function ($s) {
    return $s === '--reset';
});
if (!$index) {
    echo "Usage: docker-compose exec app php server/harvest.php {settings|epf|spins} [--reset]\n";

    return;
}

echo sprintf("Running in %s mode\n", getenv('ENV') === 'production' ? 'production' : 'development');
echo sprintf("ES host %s\n", getenv('ES_HOST'));

if ($index === 'settings') {
    echo ($reset ? 'Resetting' : 'Updating') . " settings and mappings\n";

    echo "Result:\n";
    echo json_encode(Indexes::init(null, $reset), JSON_PRETTY_PRINT);
    exit(0);
}

if ($index === Indexes::SPINS_IDX) {
    Indexes::init($index, $reset);
    SpinsHarvester::run($count);
} elseif ($index === Indexes::EPF_IDX) {
    Indexes::init($index, $reset);
    EpfHarvester::run($count);
} else {
    echo "Invalid index\n";
}
