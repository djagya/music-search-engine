#!/usr/bin/env bash

rm -rf /tmp/data_dump
mkdir -p /tmp/data_dump/spins

DUMP_SQL=$(cat <<EOF
create table spins_dump ENGINE = MyISAM
SELECT id,
    artist_name, -- artist
    release_title, COALESCE(reference_genre, song_genre) as release_genre, release_variuos_artists as release_various_artists,
    release_year_released, release_upc,  label_name, cover_art_url, release_medium, -- release
    song_name, song_isrc, spin_duration as song_duration, song_composer, spin_timestamp -- song
    FROM spin;
ALTER TABLE spins_dump ADD PRIMARY KEY (id);
EOF
)

mysql -uroot -p${MYSQL_PASS} spinitron2 -e "drop table if exists spins_dump;"
mysql -uroot -p${MYSQL_PASS} spinitron2 < "$DUMP_SQL"

myisamchk /var/lib/mysql/datadir/spinitron2/spins_dump.MYI
myisamchk -rq /var/lib/mysql/datadir/spinitron2/spins_dump

cp /var/lib/mysql/datadir/spinitron2/spins_dump* /tmp/data_dump/spins
cd /tmp/data_dump
tar -czvf spins-myisam-tables.tgz spins

mysql -uroot -p${MYSQL_PASS} spinitron2 -e "drop table if exists spins_dump;"
