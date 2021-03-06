<?php
declare(strict_types=1);

namespace app\harvest;

use app\Db;
use app\Indexes;
use app\search\BaseSearch;
use PDO;

/**
 * Spins data harvester.
 */
class SpinsHarvester extends BaseHarvester
{
    const INDEX_NAME = Indexes::SPINS_IDX;

    protected static function before(): void
    {
        parent::before();

        self::$minId = static::getDb()->query('select min(id) from spins_dump')->fetchColumn();
        self::$maxId = static::getDb()->query('select max(id) from spins_dump')->fetchColumn();
    }

    protected static function getDb(): PDO
    {
        return Db::spins();
    }

    protected function getQuery(): string
    {
        return 'select * from spins_dump where id BETWEEN ? AND ?';
    }

    /**
     * After multiple tests 7000 appeared as near optimal to increase the index speed.
     */
    protected function getBatchSize(): int
    {
        return 7000;
    }

    protected function getEsBatchBody(array $batch): array
    {
        $body = [];
        foreach ($batch as $row) {
            $emptyFields = array_filter(BaseSearch::AC_FIELDS, function ($field) use ($row) {
                return empty($row[$field]);
            });
            if ($emptyFields) {
                continue;
            }

            $body[] = ['index' => ['_index' => static::INDEX_NAME]];
            $body[] = $this->mapRow($row);
        }

        return $body;
    }

    protected function mapRow(array $row): array
    {
        if (array_key_exists('song_composer', $row)) {
            unset($row['song_composer']);
        }

        return array_map(function ($v) {
            // Remove new-line characters from data so JSON batch request is valid.
            return is_string($v) ? str_replace(["\n", "\r"], ' ', $v) : $v;
        }, $row);
    }
}
