<?php

use Search\Harvester;
use Search\Indexes;

require 'vendor/autoload.php';

Indexes::createSpins();

Harvester::run(3);
