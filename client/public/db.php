<?php

require __DIR__ . '/../../db.php';

$totalDocCount = $pdo->query('select count(id) from spins')->fetchColumn();
echo "There are $totalDocCount documents \n";
