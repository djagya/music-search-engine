#!/usr/bin/env bash

DIR=`dirname "$0"`

if [[ "$1" == "spins" ]]; then
    echo "Ingesting spins DB with the dump: $DIR/spins.sql.gz"; echo

    mysql -uroot -proot -e "create database if not exists spins character set utf8mb4 collate utf8mb4_unicode_ci;"
    yes y | gunzip -c ${DIR}/spins.sql.gz | mysql -uroot -proot music

    exit
fi

if [[ "$1" == "epf" ]]; then
    echo "Unpacking EPF database from: $DIR/epf-myisam-tables.tgz"; echo
    tar --directory /var/lib/mysql -zxvf ${DIR}/epf-myisam-tables.tgz
    echo "Now restart the DB container"; echo

    exit
fi

echo "Usage:"; echo
echo "bash load.sh {spins,epf}"; echo
echo "Where 'spins', 'epf' are the sources to load into their corresponding DB"; echo

