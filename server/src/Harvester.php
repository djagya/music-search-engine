<?php
declare(strict_types=1);

namespace Search;

class Harvester
{
    protected $forkN;
    protected $totalForks;
    protected $startFromId = 0;

    public function __construct(int $forkN, int $totalForks, int $startFromId = 0)
    {
        $this->forkN = $forkN;
        $this->totalForks = $totalForks;
        $this->startFromId = $startFromId;
    }

    public static function run(int $forksCount = 3)
    {
        $client = EsClient::build();

        // Temporary make the index more performance for insert.
        $client->indices()->putSettings([
            'index' => 'spins',
            'body' => [
                'refresh_interval' => -1,
                'number_of_replicas' => 0,
            ]
        ]);

        $maxIdResponse = $client->search([
            'index' => 'spins',
            'body' => [
                'size' => 0,
                'aggs' => [
                    'max_id' => ['max' => ['field' => 'id']],
                ],
            ],
        ]);
        $maxId = $maxIdResponse['aggregations']['max_id']['value'] ?? 0;
        $totalDocCount = Db::pdo()->query("select count(id) from spins where id > $maxId")->fetchColumn();

        echo "Total " . self::format($totalDocCount) . " documents, starting from id $maxId \n";

        // Fork multiple processes to make concurrent API requests
        $pids = [];
        for ($i = 0; $i < $forksCount; $i++) {
            $pids[] = pcntl_fork();
            if (!$pids[$i]) {
                // Here runs the children.
                echo "Children #$i: starting the harvester\n";
                $harvester = new Harvester($i, $forksCount, $maxId);
                $harvester->harvest();
                break;
            }
        }

        // Wait for finish
        for ($i = 0; $i < $forksCount; $i++) {
            pcntl_waitpid($pids[$i], $status, WUNTRACED);
        }

        // Change the settings back.
        $client->indices()->putSettings([
            'index' => 'spins',
            'body' => [
                'refresh_interval' => null,
//                'number_of_replicas' => 1, // todo: temporary disable replicas to make development faster
            ]
        ]);
        // Update replicas.
        $client->indices()->forceMerge(['index' => 'spins']);
    }

    protected function harvest(): void
    {
        $client = EsClient::build();
        $pdo = Db::pdo();

        $limit = 5000;
        $fullOffset = $limit * $this->totalForks;
        $offset = 0 + $limit * $this->forkN;

        $this->log('started, offset - ' . self::format($offset) . ', limit - ' . self::format($limit));

        // Batch index.
        $params = [
            'index' => 'spins',
            'body' => []
        ];
        do {
            $rows = $pdo->prepare("select * from spins where id > {$this->startFromId} limit ? offset ?");
            $rows->execute([$limit, $offset]);

            foreach ($rows->fetchAll() as $row) {
                $params['body'][] = [
                    'index' => [
                        '_index' => 'spins',
                        // _id is auto generated
                    ]
                ];

                $params['body'][] = $row;
            }
            if (!$params['body']) {
                $this->log('finished');
                break;
            }

            $step = self::format($offset) . ' - ' . self::format($offset + $limit);
            $this->log("batch $step");

            $client->bulk($params);
            // Prepare for a new batch
            $params = ['body' => []];

            $offset += $fullOffset;

            // todo: for now index only 1m spins to not spend time every time i change the index definition
            if ($offset > 1000000) {
                return;
            }
        } while (!empty($rows));
        // Send the last batch if it exists
        if (!empty($params['body'])) {
            $client->bulk($params);
        }
    }

    protected function log(string $message): void
    {
        echo "Harvester#{$this->forkN}: $message\n";
    }

    protected static function format(int $v): string
    {
        return number_format($v, 0, ',', '.');
    }
}
