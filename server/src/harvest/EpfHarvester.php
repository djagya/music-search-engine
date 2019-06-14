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
    g.name AS release_genre,
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
	genre g ON g.genre_id = gc.genre_id
		INNER JOIN
	song_match sm ON sm.song_id = s.song_id
WHERE
    s.is_indexable = 1
SQL;
    }

    protected function mapRow(array $row): array
    {
        // Extract release year and label from p_line.
        $pline = $row['p_line'];
        unset($row['p_line']);
        if (preg_match('/(\d{4})(\s.+)?/', $pline, $m)) {
            $row['release_year_released'] = $m[1];
            if (empty($data['label_name']) && !empty($m[2])) {
                $data['label_name'] = trim($m[2]);
            }
        }

        return $row;
    }

    protected function generateId(): bool
    {
        return true;
    }
}
