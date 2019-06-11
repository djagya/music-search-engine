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
    SpinsHarvester::run(1);
} elseif ($index === Indexes::EPF_IDX) {
    EpfHarvester::run(1);
} else {
    echo "Invalid index\n";
}
