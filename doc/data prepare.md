To connect to the docker DB: `mysql -h 127.0.0.1 -u root -p`

To create a db: 'mysql -h 127.0.0.1 -u root -p root < CREATE DATABASE IF NOT EXISTS music;'

To load the data dump: `pv data/spins.sql.gz | gunzip | mysql -u root -proot -h 127.0.0.1 music`



To prepare spin data run on `bhs3`:
```bash
mysqldump --opt  spinitron spins  | gzip > spins.sql.gz
```


To copy to the AWS instance:
```bash
rsync -a data/spins-myisam-tables.tgz  -e "ssh -i $HOME/.ssh/bachelor-search.pem" ec2-user@ec2-52-57-141-175.eu-central-1.compute.amazonaws.com:/home/ec2-user/bachelor-search/data/
```



To delete columns from original spin table loaded as a dump:

```sql
alter table spin 
    drop column playlist_id, drop column station_id, drop column artist_name, drop column artist_conductor, 
    drop column artist_performers, drop column artist_ensemble, drop column artist_local, drop column artist_custom, drop column release_classical, 
    drop column release_catalog_number, drop column release_custom, drop column song_work, drop column song_iswc, drop column spin_note,
    drop column spin_requested, drop column spin_new, drop column spin_rating, drop column spin_custom, drop column label_custom, drop column spin_cued, drop column pushed;
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
