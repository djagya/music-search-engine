To connect to the docker DB: `mysql -h 127.0.0.1 -u root -p`

To create a db: 'mysql -h 127.0.0.1 -u root -p root < CREATE DATABASE IF NOT EXISTS music;'

To load the data dump: `pv data/spins.sql.gz | gunzip | mysql -u root -proot -h 127.0.0.1 music`



To prepare spin data run on `bhs3`:
```bash
mysqldump --opt  spinitron spins  | gzip > spins.sql.gz
```







That's the query to create CSV:

```sql
SELECT id, 
    artist_name, -- artist
    release_title, COALESCE(reference_genre, song_genre) as release_genre, release_variuos_artists as release_various_artists, 
    release_year_released, release_upc,  label_name, cover_art_url, release_medium, -- release
    song_name, song_isrc, spin_duration as song_duration, song_composer -- song
INTO OUTFILE '/tmp/spins.sql'
FROM spinitron2.spin;
```
