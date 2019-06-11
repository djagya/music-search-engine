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

$forks = 5;
if ($index === Indexes::SPINS_IDX) {
    SpinsHarvester::run($forks);
} elseif ($index === Indexes::EPF_IDX) {
    EpfHarvester::run($forks);
} else {
    echo "Invalid index\n";
}
