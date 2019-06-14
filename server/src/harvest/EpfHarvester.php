<?php
declare(strict_types=1);

namespace Search\harvest;

use Search\Db;
use Search\Indexes;

class EpfHarvester extends BaseHarvester
{
    const INDEX_NAME = Indexes::EPF_IDX;

    /** @var array [genre id => name] */
    private static $genresMap = [];

    protected static function before(): void
    {
        parent::before();

        $genres = static::getDb()->query('select genre_id, name from genre;')->fetchAll();
        static::$genresMap = array_column($genres, 'name', 'genre_id');
        echo sprintf("Loaded genres: %i \n", count(static::$genresMap));
    }

    protected static function getDb(): \PDO
    {
        return Db::epf();
    }

    // todo: rewrite to optimize
    protected function getQuery(): string
    {
        return <<<SQL
SELECT
    -- artist
    a.artist_id AS artist_id,
    a.name AS artist_name,
    -- release
    c.collection_id AS collection_id,
    c.name AS release_title,
    c.artwork_url as cover_art_url,
    c.label_studio AS label_name,
    c.p_line,
    -- song
    s.song_id AS song_id,
    s.name AS song_name,
    s.track_length AS song_duration,
    sm.isrc AS song_isrc
FROM
    song s
        INNER JOIN
	song_match sm ON sm.song_id = s.song_id
        INNER JOIN
    artist_song ag ON ag.song_id = s.song_id
        INNER JOIN
    artist a ON a.artist_id = ag.artist_id
        INNER JOIN
    collection_song cs ON cs.song_id = s.song_id
        INNER JOIN
    collection c ON c.collection_id = cs.collection_id
        INNER JOIN
    artist_collection ac ON ac.artist_id = a.artist_id AND ac.collection_id = c.collection_id 
WHERE
    s.is_indexable = 1 AND ac.role_id IN (1, 7)
SQL;
    }

    protected function getEsBatchBody(array $batch): array
    {
        $db = static::getDb();

        $collectionIds = implode(',', array_unique(array_column($batch, 'collection_id')));
        $query = "select collection_id, genre_id from genre_collection gc "
            . "where gc.collection_id IN ($collectionIds) AND gc.is_primary = 1";
        $collectionGenreMap = array_column($db->query($query)->fetchAll(), 'genre_id', 'collection_id');

        $query = "select collection_id, upc from collection_match cm where cm.collection_id IN ($collectionIds)";
        $collectionUpcMap = array_column($db->query($query)->fetchAll(), 'upc', 'collection_id');

        $query = "select collection_id, COUNT(artist_id) > 1 as va FROM artist_collection ac"
            ." WHERE collection_id IN ($collectionIds) group by collection_id";
        $collectionVaMap = array_column($db->query($query)->fetchAll(), 'va', 'collection_id');

        $body = [];
        foreach ($batch as $row) {
            // Extract release year and label from p_line.
            $pline = $row['p_line'];
            unset($row['p_line']);
            if (preg_match('/(\d{4})(\s.+)?/', $pline, $m)) {
                $row['release_year_released'] = $m[1];
                if (empty($row['label_name']) && !empty($m[2])) {
                    $row['label_name'] = trim($m[2]);
                }
            }

            $cId = $row['collection_id'];
            $row['release_genre'] = static::$genresMap[$collectionGenreMap[$cId]] ?? '';
            $row['release_upc'] = $collectionUpcMap[$cId] ?? '';
            $row['release_various_artists'] = $collectionVaMap[$cId] ?? '0';

            // ES payload
            $body[] = ['index' => ['_index' => static::INDEX_NAME]];
            $body[] = $row;
        }

        return $body;
    }
}
