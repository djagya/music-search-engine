#!/usr/bin/env bash

DIR=`dirname "$0"`

if [[ "$1" == "spins.sql" ]]; then
    echo "Ingesting spins DB with the dump: $DIR/spins.sql.gz"; echo
    apt-get update
    apt-get install pv

    mysql -uroot -p${MYSQL_ROOT_PASSWORD} -e "create database if not exists spins character set utf8mb4 collate utf8mb4_unicode_ci;"
    pv ${DIR}/spins.sql.gz | gunzip | mysql -uroot -p${MYSQL_ROOT_PASSWORD} spins

    exit
fi

if [[ "$1" == "spins" ]]; then
    echo "Unpacking Spins database from: $DIR/spins-myisam-tables.tgz"; echo
    tar --directory /var/lib/mysql -zxvf ${DIR}/spins-myisam-tables.tgz
    echo "Now restart the DB container:";
    echo "docker-compose restart db"

    exit
fi

if [[ "$1" == "epf" ]]; then
    echo "Unpacking EPF database from: $DIR/epf-myisam-tables.tgz"; echo
    tar --directory /var/lib/mysql -zxvf ${DIR}/epf-myisam-tables.tgz
    echo "Now restart the DB container:";
    echo "docker-compose restart db"

    exit
fi

echo "Usage:"; echo
echo "bash load.sh {spins,epf}"; echo
echo "Where 'spins', 'epf' are the sources to load into their corresponding DB"; echo

