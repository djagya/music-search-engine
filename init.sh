#!/usr/bin/env bash

if [[ "$CHECK_DB" = "true" ]]; then
    echo "Check the music DB '$DB_NAME' on the host $MYSQL_HOST"
    sleep 20

    DB_EXISTS=$(mysql -u root -proot -h ${MYSQL_HOST} -e "show databases like 'music';");
    if [[ -z ${DB_EXISTS} ]]; then
        echo "Create the '$DB_NAME' DB"
        mysql -u root -proot -h ${MYSQL_HOST} -e "SET default_storage_engine=MYISAM;"
        mysql -u root -proot -h ${MYSQL_HOST} \
            -e "create database ${DB_NAME} character set utf8mb4 collate utf8mb4_unicode_ci;"
    fi
fi

if [[ "$RUN_SERVER" = "true" ]]; then
    echo "Start server"
    nohup php -S 0.0.0.0:80 -t client/build server/index.php > logs/server.log 2>&1 &
fi
