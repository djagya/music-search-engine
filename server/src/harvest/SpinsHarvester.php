<?php
declare(strict_types=1);

namespace app\harvest;

use app\Db;
use app\Indexes;
use PDO;

/**
 * When harvesting Spins, their original ids are used as ES "_id" field.
 * Not all columns from the 'spin' table are needed to be indexed, so each row is mapped using
 * {@see SpinsHarvester::mapRow()}
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

    protected function getEsBatchBody(array $batch): array
    {
        $body = [];
        foreach ($batch as $row) {
            $body[] = [
                'index' => [
                    '_index' => static::INDEX_NAME,
                ],
            ];

            $body[] = $this->mapRow($row);
        }

        return $body;
    }

    protected function generateId(): bool
    {
        return false;
    }

    protected function mapRow(array $row): array
    {
        return $row;
    }
}
