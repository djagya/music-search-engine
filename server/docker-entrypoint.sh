#!/usr/bin/env bash

if [[ "$CHECK_DB" -eq "1" ]]; then
    echo "Check the music DB 'music' on the host $MYSQL_HOST"
    sleep 2

    mysql -u root -proot -h ${MYSQL_HOST} -e "SET default_storage_engine=MYISAM;"
    mysql -u root -proot -h ${MYSQL_HOST} \
        -e "create database if not exists music character set utf8mb4 collate utf8mb4_unicode_ci;"
    mysql -u root -proot -h ${MYSQL_HOST} \
        -e "create database if not exists epf character set utf8mb4 collate utf8mb4_unicode_ci;"
fi

if [[ "$RUN_SERVER" -eq "1" ]]; then
    echo "Start nginx and run php-fpm"
    service nginx start

    php-fpm
fi

