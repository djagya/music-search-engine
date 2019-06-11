<?php
declare(strict_types=1);

namespace Search\harvest;

use Search\Db;
use Search\EsClient;

/**
 * When harvesting Spins, their original ids are used as ES "_id" field.
 * Not all columns from the 'spin' table are needed to be indexed, so each row is mapped using {@see SpinsHarvester::mapRow()}
 */
class SpinsHarvester extends BaseHarvester
{
    const INDEX_NAME = 'spins';

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
        self::$totalCount = Db::spins()->query('select count(id) from spins where id > ' . self::$startFromId)->fetchColumn();

        echo "Total " . self::format(self::$totalCount) . " documents, starting from id " . self::$startFromId . "\n";
    }

    protected function getDb(): \PDO
    {
        return Db::spins();
    }

    protected function getQuery(): string
    {
        return  'select * from spins where id > ' . self::$startFromId . ' limit ? offset ?';
    }

    protected function generateId(): bool
    {
        return false;
    }

    protected function mapRow(array $row): array
    {
        $fields = [
            'id',
            // Main index fields
            'artist_name',
            'song_name',
            'release_title',
            // Metadata
            'artist_conductor',
            'artist_performers',
            'artist_ensemble',
            'song_genre',
            'reference_genre',
            'release_medium',
            'release_various_artists',
            'release_date_added',
            'release_classical',
            'release_catalog_number',
            'release_year_released',
            'release_upc',
            'song_work',
            'song_composer',
            'song_isrc',
            'song_iswc',
            'spin_duration',
            'label_name',
            'cover_art_url',
        ];

        return array_intersect_assoc($row, array_flip($fields));
    }
}
