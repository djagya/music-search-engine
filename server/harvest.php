<?php

use Search\harvest\EpfHarvester;
use Search\harvest\SpinsHarvester;
use Search\Indexes;

require 'vendor/autoload.php';

$index = $argv[1] ?? null;
if (!$index) {
    echo "Usage: docker-compose exec app php server/harvest.php {epf|spins}";
    return;
}

Indexes::init($index);

if ($index === Indexes::SPINS_IDX) {
    SpinsHarvester::run(5);
} elseif ($index === Indexes::EPF_IDX) {
    // EPF data are expensive to load and won't gain from multiple running harvesters.
    EpfHarvester::run(1);
} else {
    echo "Invalid index\n";
}
