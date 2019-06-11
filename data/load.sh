#!/usr/bin/env bash

DIR=`dirname "$0"`

if [[ "$1" == "spins" ]]; then
    echo "Ingesting music DB with spins dump"; echo

    mysql -uroot -proot -e "show databases like 'music';"
    gunzip ${DIR}/spins.sql.gz | mysql -uroot -proot music

    exit
fi

if [[ "$1" == "epf" ]]; then
    echo "Unpacking EPF database"; echo
    tar --directory /var/lib/mysql -zxvf ${DIR}/epf-myisam-lables.tgz
    echo "Now restart the DB container"; echo

    exit
fi

echo "Usage:"; echo
echo "bash load.sh {music,epf}"; echo
echo "Where music, epf are the sources to load into their corresponding DB"; echo

