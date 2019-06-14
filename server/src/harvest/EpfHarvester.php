<?php
declare(strict_types=1);

namespace Search\harvest;

use Search\Db;
use Search\EsClient;
use Search\Indexes;

class EpfHarvester extends BaseHarvester
{
    const INDEX_NAME = Indexes::EPF_IDX;
    const BATCH_SIZE = 5000;

    private static $minSongId;
    private static $maxSongId;

    /** @var array [genre id => name] */
    private static $genresMap = [];

    protected static function before(): void
    {
        parent::before();

        self::$minSongId = static::getDb()->query('select min(song_id) from song')->fetchColumn();
        self::$maxSongId = static::getDb()->query('select max(song_id) from song')->fetchColumn();

        $genres = static::getDb()->query('select genre_id, name from genre;')->fetchAll();
        static::$genresMap = array_column($genres, 'name', 'genre_id');
        echo sprintf("Loaded genres: %d \n", count(static::$genresMap));
    }

    protected static function getDb(): \PDO
    {
        return Db::epf();
    }

    protected function harvest(): void
    {
        $client = EsClient::build();
        $pdo = static::getDb();

        $fromId = self::$minSongId + self::BATCH_SIZE * $this->forkN; // forkN 0 - from id 0
        $toId = $fromId + self::BATCH_SIZE;
        $step = self::BATCH_SIZE * $this->totalForks;

        $this->log(sprintf('started from ID %d to ID %d, max song id %d', $fromId, $toId, self::$maxSongId));

        $query = $this->getQuery() . ' AND s.song_id BETWEEN ? AND ?';
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

            if (!$rows->rowCount()) {
                continue;
            }

            // Convert to ES query body.
            $params['body'] = $this->getEsBatchBody($rows->fetchAll());

            // Send the BULK request to ES.
            $client->bulk($params);
            $this->log(sprintf('batch %d – %d out of %d', $fromId, $toId, self::$maxSongId));

            // Prepare for a new batch.
            $params = ['body' => []];
            $fromId += $step;
            $toId += $step;

            if (getenv('ENV') !== 'production' && $indexedCount > static::DEV_LIMIT) {
                echo "Dev limit $indexedCount > " . static::DEV_LIMIT . "\n";

                return;
            }
        } while ($fromId <= self::$maxSongId);
        $this->log('harvest is finished');
    }

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
    gc.genre_id AS release_genre,
    (SELECT COUNT(artist_id) FROM artist_collection ac_1 WHERE ac_1.collection_id = c.collection_id)  > 1 AS release_various_artists,
    cm.upc as release_upc,
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
    artist_song ag ON ag.song_id = s.song_id
        INNER JOIN
    artist a ON a.artist_id = ag.artist_id
        INNER JOIN
    collection_song cs ON cs.song_id = s.song_id
        INNER JOIN
    collection c ON c.collection_id = cs.collection_id
        INNER JOIN
	collection_match cm ON cm.collection_id = c.collection_id
        INNER JOIN
    artist_collection ac ON ac.artist_id = a.artist_id AND ac.collection_id = c.collection_id AND ac.role_id IN (1, 7)
		INNER JOIN
	genre_collection gc ON gc.collection_id = c.collection_id AND gc.is_primary = 1
		INNER JOIN
	song_match sm ON sm.song_id = s.song_id
WHERE
    s.is_indexable = 1
SQL;
    }

    protected function getEsBatchBody(array $batch): array
    {
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

            // Convert genre id to name.
            $row['release_genre'] =
                !empty($row['release_genre']) ? (self::$genresMap[$row['release_genre']] ?? '') : '';

            // ES payload
            $body[] = ['index' => ['_index' => static::INDEX_NAME]];
            $body[] = $row;
        }

        return $body;
    }

    // todo: rewrite to optimize
    protected function getQuery1(): string
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

    protected function getEsBatchBody1(array $batch): array
    {
        $db = static::getDb();

        $collectionIds = implode(',', array_unique(array_column($batch, 'collection_id')));
        $query = "select collection_id, genre_id from genre_collection gc "
            . "where gc.collection_id IN ($collectionIds) AND gc.is_primary = 1";
        $collectionGenreMap = array_column($db->query($query)->fetchAll(), 'genre_id', 'collection_id');

        $query = "select collection_id, upc from collection_match cm where cm.collection_id IN ($collectionIds)";
        $collectionUpcMap = array_column($db->query($query)->fetchAll(), 'upc', 'collection_id');

        $query = "select collection_id, COUNT(artist_id) > 1 as va FROM artist_collection ac"
            . " WHERE collection_id IN ($collectionIds) group by collection_id";
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
            $row['release_genre'] = static::$genresMap[$collectionGenreMap[$cId] ?? ''] ?? '';
            $row['release_upc'] = $collectionUpcMap[$cId] ?? '';
            $row['release_various_artists'] = $collectionVaMap[$cId] ?? '0';

            // ES payload
            $body[] = ['index' => ['_index' => static::INDEX_NAME]];
            $body[] = $row;
        }

        return $body;
    }
}
