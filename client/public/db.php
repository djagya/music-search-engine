<?php

require __DIR__ . '/../../db.php';
$pdo = getPdo();
$totalDocCount = $pdo->query('select count(id) from spins')->fetchColumn();
echo "There are $totalDocCount documents \n";
