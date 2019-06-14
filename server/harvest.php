<?php

use Search\harvest\EpfHarvester;
use Search\harvest\SpinsHarvester;
use Search\Indexes;

require 'vendor/autoload.php';

$index = $argv[1] ?? null;
$count = $argv[2] ?? 1;
if (!$index) {
    echo "Usage: docker-compose exec app php server/harvest.php {epf|spins}\n";

    return;
}

echo sprintf("Running in %s mode\n", getenv('ENV') === 'production' ? 'production' : 'development');

Indexes::init($index);

if ($index === Indexes::SPINS_IDX) {
    SpinsHarvester::run($count);
} elseif ($index === Indexes::EPF_IDX) {
    // EPF data are expensive to load and won't gain from multiple running harvesters.
    EpfHarvester::run($count);
} else {
    echo "Invalid index\n";
}
