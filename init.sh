#!/usr/bin/env bash

sleep 20

echo "Check the 'music' DB"
DB_EXISTS=$(mysql -u root -proot -h ${MYSQL_HOST} -e "show databases like 'music';");
if [[ -z ${DB_EXISTS} ]]; then
    echo "Create the 'music' DB"
    mysql -u root -proot -h ${MYSQL_HOST} -e "SET default_storage_engine=MYISAM;"
    mysql -u root -proot -h ${MYSQL_HOST} \
        -e "create database ${DB_NAME} character set utf8mb4 collate utf8mb4_unicode_ci;"
else
    echo "The DB is already created"
fi

echo "Start server"
php -S 0.0.0.0:80 -t client/build server/index.php 2>&1 | tee -a logs/server.log
