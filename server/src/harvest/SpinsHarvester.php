<?php
declare(strict_types=1);

namespace Search\harvest;

use Search\Db;
use Search\Indexes;

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

        self::$minId = static::getDb()->query('select min(id) from spin')->fetchColumn();
        self::$maxId = static::getDb()->query('select max(id) from spin')->fetchColumn();
    }

    protected static function getDb(): \PDO
    {
        return Db::spins();
    }

    protected function getQuery(): string
    {
        return 'select * from spin where id BETWEEN ? AND ?';
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
        // Common for spins and EPF fields.
        $fields = [
            'id',
            // Artist
            'artist_name',
            // Release
            'release_title',
            'release_genre',
            'release_various_artists',
            'release_year_released',
            'release_upc',
            'label_name',
            'cover_art_url',
            // Song
            'song_name',
            'song_isrc',
            'song_duration',
        ];

        // Additional spins data source fields not presented in EPF.
        $fields = array_merge($fields, [
            'spin_timestamp',
            'release_medium',
            'song_composer',
        ]);

        return array_intersect_key($row, array_flip($fields));
    }
}
