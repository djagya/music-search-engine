<?php
declare(strict_types=1);

namespace Search\harvest;

use Search\EsClient;

abstract class BaseHarvester
{
    const INDEX_NAME = '';

    const BATCH_SIZE = 5000;
    const DEV_LIMIT = 1000000;

    protected $forkN;
    protected $totalForks;

    /**
     * Spawn multiple harvesters to go over the data source and send batch ES index requests concurrently.
     * @param int $forksCount
     */
    public static function run(int $forksCount = 3): void
    {
        static::before();

        // Fork multiple processes to make concurrent API requests
        $forks = [];
        for ($i = 1; $i <= $forksCount; $i++) {
            $forks[] = pcntl_fork();
            if (!$forks[$i]) {
                // Here runs the children.
                echo "Children #$i: starting the harvester\n";
                $harvester = new static($i, $forksCount);
                $harvester->harvest();
                break;
            }
        }
        // Wait for all forks to finish.
        for ($i = 0; $i < $forksCount; $i++) {
            pcntl_waitpid($forks[$i], $status, WUNTRACED);
        }


        static::after();
    }

    /**
     * Run code before starting the harvester.
     */
    protected static function before(): void
    {
        // Temporary make the index more performance for insert.
        EsClient::build()->indices()->putSettings([
            'index' => self::INDEX_NAME,
            'body' => [
                'refresh_interval' => -1,
                'number_of_replicas' => 0,
            ]
        ]);
    }

    /**
     * Run code after the harvester has finished.
     */
    protected static function after(): void
    {
        $client = EsClient::build();
        // Change the settings back.
        $client->indices()->putSettings([
            'index' => self::INDEX_NAME,
            'body' => [
                'refresh_interval' => null,
                // 'number_of_replicas' => 1, // todo: temporary disable replicas to make development faster
            ]
        ]);

        // Update replicas.
        $client->indices()->forceMerge(['index' => self::INDEX_NAME]);
    }

    /**
     * BaseHarvester constructor.
     * @param int $forkN used to determine the initial offset: init offset = $forkN * BATCH_SIZE
     * @param int $totalForks used to calculate the offset step: offset step = $totalForks * BACH_SIZE
     */
    public function __construct(int $forkN, int $totalForks)
    {
        $this->forkN = $forkN;
        $this->totalForks = $totalForks;
    }

    /**
     * Start the harvest process.
     * todo: maybe sequential access is faster for the DB to process?
     */
    protected function harvest(): void
    {
        $client = EsClient::build();
        $pdo = $this->getDb();

        $limit = self::BATCH_SIZE;
        $fullOffset = $limit * $this->totalForks;
        $offset = 0 + $limit * $this->forkN;

        $this->log('started, offset - ' . self::format($offset) . ', limit - ' . self::format($limit));

        $query = $this->getQuery();
        $params = [
            'index' => self::INDEX_NAME,
            'body' => []
        ];
        do {
            // Fetch the next data batch.
            $rows = $pdo->prepare($query);
            $rows->execute([$limit, $offset]);

            // Prepare the data batch.
            foreach ($rows->fetchAll() as $row) {
                $params['body'][] = [
                    'index' => [
                        '_index' => self::INDEX_NAME,
                        '_id' => $this->generateId() ? null : $row['id']
                    ]
                ];
                $params['body'][] = $this->mapRow($row);
            }
            // Finish when no rows in the batch.
            if (!$params['body']) {
                $this->log('harvest is finished');
                break;
            }

            // Send the BULK request to ES.
            $client->bulk($params);
            $this->log("batch " . self::format($offset) . ' - ' . self::format($offset + $limit));

            // Prepare for a new batch.
            $params = ['body' => []];
            $offset += $fullOffset;

            // todo: for now index only 1m spins to not spend time every time i change the index definition
            if ($offset > static::DEV_LIMIT) {
                return;
            }
        } while (!empty($rows));
    }

    abstract protected function getDb(): \PDO;

    abstract protected function getQuery(): string;

    abstract protected function mapRow(array $row): array;

    abstract protected function generateId(): bool;

    protected function log(string $message): void
    {
        echo "Harvester#{$this->forkN}: $message\n";
    }

    protected static function format(int $v): string
    {
        return number_format($v, 0, ',', '.');
    }
}
