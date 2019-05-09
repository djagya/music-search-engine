#!/usr/bin/env bash

DB_NAME="music"
DIR=`dirname "$0"`
mysql -uroot -proot -e "show databases like '$DB_NAME';"
gunzip ./data/spins.sql.gz | mysql -uroot -proot ${DB_NAME}
