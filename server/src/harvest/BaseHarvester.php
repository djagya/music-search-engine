<?php
declare(strict_types=1);

namespace app\harvest;

use app\EsClient;
use PDO;
use Throwable;

abstract class BaseHarvester
{
    const INDEX_NAME = '';

    const BATCH_SIZE = 5000;
    const DEV_LIMIT = 200000;

    protected static $minId;
    protected static $maxId;

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
        for ($i = 0; $i < $forksCount; $i++) {
            $forks[] = pcntl_fork();
            if (!$forks[$i]) {
                // Here runs the children.
                echo "Children #$i: starting the harvester\n";
                $harvester = new static($i, $forksCount);
                $harvester->harvest();

                return;
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
            'index' => static::INDEX_NAME,
            'body' => [
                'refresh_interval' => -1,
                'number_of_replicas' => 0,
            ],
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
            'index' => static::INDEX_NAME,
            'body' => [
                'refresh_interval' => null,
            ],
        ]);

        // Update replicas.
        $client->indices()->forceMerge(['index' => static::INDEX_NAME]);
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
        $pdo = static::getDb();

        $fromId = self::$minId + self::BATCH_SIZE * $this->forkN; // forkN 0 - from id 0
        $toId = $fromId + self::BATCH_SIZE;
        $step = self::BATCH_SIZE * $this->totalForks;

        $this->log(sprintf('started from ID %s to ID %s, max song id %s', self::format($fromId), self::format($toId),
            self::format(self::$maxId)));

        $query = $this->getQuery();
        $params = [
            'index' => static::INDEX_NAME,
            'body' => [],
        ];
        $indexedCount = 0;
        do {
            // Fetch the next data batch.
            $rows = $pdo->prepare($query);
            $rows->execute([$fromId, $toId]);

            $indexedCount += $rows->rowCount();
            $fromId += $step;
            $toId += $step;

            if (!$rows->rowCount()) {
                continue;
            }

            // Convert to ES query body.
            $params['body'] = $this->getEsBatchBody($rows->fetchAll());

            if (!empty($params['body'])) {
                // Send the BULK request to ES.
                try {
                    $client->bulk($params);
                } catch (Throwable $e) {
                    $this->log("Error: {$e->getMessage()}");
                }
            }
            $this->log(sprintf('batch %s – %s out of %s', self::format($fromId), self::format($toId),
                self::format(self::$maxId)));

            // Prepare for a new batch.
            $params = ['body' => []];

            if (getenv('ENV') !== 'production' && $indexedCount > static::DEV_LIMIT) {
                echo "Dev limit $indexedCount > " . static::DEV_LIMIT . "\n";

                return;
            }
        } while ($fromId <= self::$maxId);
        $this->log('harvest is finished');
    }

    abstract protected function getEsBatchBody(array $batch): array;

    abstract static protected function getDb(): PDO;

    abstract protected function getQuery(): string;

    protected function log(string $message): void
    {
        echo gmdate('Y-m-d H:i:s') . " Harvester#{$this->forkN}: $message\n";
    }

    protected static function format(int $v): string
    {
        return number_format($v, 0, ',', '.');
    }
}
