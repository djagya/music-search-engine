<?php
declare(strict_types=1);

namespace Search\harvest;

use Search\Db;
use Search\EsClient;
use Search\Indexes;

/**
 * When harvesting Spins, their original ids are used as ES "_id" field.
 * Not all columns from the 'spin' table are needed to be indexed, so each row is mapped using
 * {@see SpinsHarvester::mapRow()}
 */
class SpinsHarvester extends BaseHarvester
{
    const INDEX_NAME = Indexes::SPINS_IDX;

    static private $totalCount = 0;
    static private $startFromId = 0;

    /**
     * Additionally find out the max indexed ID and total documents count starting from that max ID.
     */
    protected static function before(): void
    {
        parent::before();

        $maxIdResponse = EsClient::build()->search([
            'index' => self::INDEX_NAME,
            'body' => [
                'size' => 0,
                'aggs' => [
                    'max_id' => ['max' => ['field' => 'id']],
                ],
            ],
        ]);
        self::$startFromId = $maxIdResponse['aggregations']['max_id']['value'] ?? 0;
        self::$totalCount =
            Db::spins()->query('select count(id) from spins where id > ' . self::$startFromId)->fetchColumn();

        echo "Total " . self::format(self::$totalCount) . " documents, starting from id " . self::$startFromId . "\n";
    }

    protected function getDb(): \PDO
    {
        return Db::spins();
    }

    protected function getQuery(): string
    {
        return 'select * from spins where id > ' . self::$startFromId;
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
            'release_medium',
            'song_composer',
        ]);


        return array_intersect_key($row, array_flip($fields));
    }
}
