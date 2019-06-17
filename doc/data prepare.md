To copy to the AWS instance:
```bash
rsync -azP data/spins-myisam-tables.tgz  -e "ssh -i $HOME/.ssh/bachelor-search.pem" ec2-user@ec2-52-57-141-175.eu-central-1.compute.amazonaws.com:/home/ec2-user/bachelor-search/data/
```

That's the query to create the spins_dump table on bhs3:

```sql
create table spins_dump ENGINE = MyISAM 
SELECT id, 
    artist_name, -- artist
    release_title, COALESCE(reference_genre, song_genre) as release_genre, release_variuos_artists as release_various_artists, 
    release_year_released, release_upc,  label_name, cover_art_url, release_medium, -- release
    song_name, song_isrc, spin_duration as song_duration, song_composer, spin_timestamp -- song
    FROM spin;
ALTER TABLE spins_dump ADD PRIMARY KEY (id);
```

Then to pack it:

```bash
myisamchk /var/lib/mysql/datadir/spinitron2/spins_dump.MYI
myisamchk -rq /var/lib/mysql/datadir/spinitron2/spins_dump

cd /tmp && tar -C /var/lib/mysql/datadir/spinitron2/ -czvf spins-myisam-tables.tgz /var/lib/mysql/datadir/spinitron2/spins_dump*
```

Then to copy to my machine:

```bash
rsync -azP root@bhs3:/tmp/spins-myisam-tables.tgz data/
```
