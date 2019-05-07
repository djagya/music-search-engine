To connect to the docker DB: `mysql -h 127.0.0.1 -u root -p`

To create a db: 'mysql -h 127.0.0.1 -u root -p root < CREATE DATABASE IF NOT EXISTS music;'

To load the data dump: `pv data/spins.sql.gz | gunzip | mysql -u root -proot -h 127.0.0.1 music`
