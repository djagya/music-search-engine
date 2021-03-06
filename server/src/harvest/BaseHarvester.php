<?php
declare(strict_types=1);

namespace app\harvest;

use app\EsClient;
use PDO;
use Throwable;

/**
 * Main harvester class implementing harvesting and indexing functionality.
 * Should be extended for each separate data source.
 */
abstract class BaseHarvester
{
    const INDEX_NAME = '';

    const DEV_LIMIT = 1000000;

    protected static $minId;
    protected static $maxId;
    protected static $startTime;

    protected $forkN;
    protected $totalForks;

    /**
     * Spawn multiple harvesters to go over the data source and send batch ES index requests concurrently.
     * @param int $forksCount
     */
    public static function run(int $forksCount = 3, ?int $limit = null, ?int $batchSize = null): void
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
                $harvester->harvest($limit, $batchSize);

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

    protected abstract function getBatchSize(): int;

    /**
     * Start the harvest process.
     */
    protected function harvest(?int $limit = null, ?int $batchSize = null): void
    {
        $client = EsClient::build();
        $pdo = static::getDb();

        $batchSize = $batchSize ?: $this->getBatchSize();
        $fromId = self::$minId + $batchSize * $this->forkN; // forkN 0 - from id 0
        $toId = $fromId + $batchSize;
        $step = $batchSize * $this->totalForks;

        $totalBatches = (int) ceil(($limit ?? (self::$maxId - $fromId)) / $step);

        $this->log(sprintf('batch size %s, limit %s, total batches %s', self::format($batchSize),
            $limit ? self::format($limit) : 'unlimited', self::format($totalBatches)));
        $this->log(sprintf('started from ID %s to ID %s, max song id %s', self::format($fromId), self::format($toId),
            self::format(self::$maxId)));

        // Performance log.
        $perfLog = $this->getPerfomanceLogHandle($batchSize, $limit ?? 0);

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
            $esBodyTime = microtime(true);
            $params['body'] = $this->getEsBatchBody($rows);
            $esBodyTime = microtime(true) - $esBodyTime;

            $bulkTime = null;
            try {
                if (!empty($params['body'])) {
                    $bulkTime = microtime(true);
                    // Send the BULK request to ES.
                    $client->bulk($params);
                    $bulkTime = microtime(true) - $bulkTime;
                }
            } catch (Throwable $e) {
                $this->log(sprintf("Error on batch %s – %s: %s", self::format($fromId), self::format($toId),
                    $e->getMessage()));
            }
            if ($batchN % 100 === 0) {
                $this->log(sprintf('batch %s, took %f, indexed %s', self::format($batchN),
                    $batchTime, self::format($indexedCount)));
            }

            if ($perfLog) {
                fputcsv($perfLog, [time(), $batchTime, $esBodyTime, $bulkTime]);
            }

            // Prepare for a new batch.
            $params = ['body' => []];

            if (getenv('ENV') !== 'production' && $indexedCount > static::DEV_LIMIT) {
                echo "Dev limit $indexedCount > " . static::DEV_LIMIT . "\n";

                return;
            }
            if ($limit && $indexedCount > $limit) {
                echo "Limit $limit is reached\n";

                return;
            }
        } while ($fromId <= self::$maxId);
        $this->log('harvest is finished');
    }

    /**
     * Return the performance log file handle where stats are written for each processed batch.
     * It's it available only for the first fork.
     * @param int $batchSize
     * @param int $limit
     * @return bool|resource
     */
    private function getPerfomanceLogHandle(int $batchSize, int $limit)
    {
        if ($this->forkN !== 0) {
            return false;
        }

        $path = __DIR__ . '/../../../logs/harvest/';
        if (!is_dir($path)) {
            mkdir($path);
        }
        $logName = static::INDEX_NAME . "_{$batchSize}_{$limit}.log";
        $perfLog = fopen($path . $logName, 'w+');
        fputcsv($perfLog, ['timestamp', 'batchTime', 'transformTime', 'bulkTime']);

        return $perfLog;
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
