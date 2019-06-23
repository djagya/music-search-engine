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
    protected static $startTime;

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
        static::$startTime = microtime(true);

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
        $index = static::INDEX_NAME;
        $client = EsClient::build();
        // Change the settings back.
        $client->indices()->putSettings([
            'index' => $index,
            'body' => [
                'refresh_interval' => null,
            ],
        ]);
        // Update replicas.
        $client->indices()->forceMerge(['index' => $index]);

        $totalTime = microtime(true) - static::$startTime;
        $m = floor($totalTime / 60);
        $readableTime = ($m > 0 ? "{$m}m" : '') . round($totalTime % 60) . 's';

        sleep(5);
        $stats = $client->indices()->stats(['index' => $index])['indices'][$index]['total'];
        $count = $stats['docs']['count'];
        $size = $stats['store']['size_in_bytes'] / 1024 / 1024;

        echo "\n----------------------------------\n";
        echo sprintf("Time\tDoc count\tSize (MB)\n");
        echo sprintf("%-s\t%-9s\t%-.2f\n", $readableTime, number_format($count), $size);
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
        $totalBatches = (int) ceil((self::$maxId - $fromId) / $step);

        $this->log(sprintf('started from ID %s to ID %s, max song id %s', self::format($fromId), self::format($toId),
            self::format(self::$maxId)));
        $this->log(sprintf('total batches: %s', self::format($totalBatches)));

        $query = $this->getQuery();
        $params = [
            'index' => static::INDEX_NAME,
            'body' => [],
        ];
        $indexedCount = 0;
        $batchN = 0;
        do {
            // Fetch the next data batch.
            $batchTime = microtime(true);
            $rows = $pdo->prepare($query);
            $rows->execute([$fromId, $toId]);
            $rows = $rows->fetchAll();
            $indexedCount += count($rows);
            $batchTime = microtime(true) - $batchTime;

            $fromId += $step;
            $toId += $step;
            $batchN += 1;

            if (!$rows) {
                continue;
            }

            // Convert to ES query body.
            $params['body'] = $this->getEsBatchBody($rows);

            try {
                if (!empty($params['body'])) {
                    // Send the BULK request to ES.
                    $client->bulk($params);
                }
            } catch (Throwable $e) {
                $this->log(sprintf("Error on batch %s – %s: %s", self::format($fromId), self::format($toId),
                    $e->getMessage()));
            }
            if ($batchN % 100 === 0) {
                $this->log(sprintf('batch %s out of %s, took %f', self::format($batchN), self::format($totalBatches),
                    $batchTime));
            }

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
