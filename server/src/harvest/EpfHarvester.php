<?php
declare(strict_types=1);

namespace Search\harvest;

use Search\Db;
use Search\Indexes;

class EpfHarvester extends BaseHarvester
{
    const INDEX_NAME = Indexes::EPF_IDX;

    protected function getDb(): \PDO
    {
        return Db::epf();
    }

    /**
     * Ignore composer as it will make the data to index too large.
     */
    protected function getQuery(): string
    {
        // todo: add metadata and corresponding joins
        return <<<SQL
SELECT 
    s.song_id AS song_id,
    s.name AS song_name,
    a.artist_id AS artist_id,
    a.name AS artist_name,
    c.collection_id AS collection_id,
    c.name AS release_title
FROM
    song s
        INNER JOIN
    artist_song ag ON ag.song_id = s.song_id
        INNER JOIN
    artist a ON a.artist_id = ag.artist_id
        INNER JOIN
    collection_song cs ON cs.song_id = s.song_id
        INNER JOIN
    collection c ON c.collection_id = cs.collection_id
        JOIN
    artist_collection ac ON ac.artist_id = a.artist_id
        AND ac.collection_id = c.collection_id
WHERE
    s.is_indexable = 1 AND ac.role_id IN (1, 7)
limit ? offset ?
SQL;
    }

    protected function mapRow(array $row): array
    {
        return $row;
    }

    protected function generateId(): bool
    {
        return true;
    }
}
