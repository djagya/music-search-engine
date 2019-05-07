#!/usr/bin/env bash

sleep 20
echo "Prepare DB"
DB_LOADED=$(mysql -u root -proot -h ${MYSQL_HOST} -e "show databases like 'music';");

if [[ -z ${DB_LOADED} ]]; then
    echo "Init DB"
    mysql -u root -proot -h ${MYSQL_HOST} -e "SET default_storage_engine=MYISAM;"
    mysql -u root -proot -h ${MYSQL_HOST} \
        -e "create database ${DB_NAME} character set utf8mb4 collate utf8mb4_unicode_ci;"
    pv ./data/spins.sql.gz | gunzip | mysql -u root -proot -h ${MYSQL_HOST} music
else
    echo "Already loaded"
fi

echo "Install packages"
php /usr/bin/composer.phar install

echo "Starting the server"
php -S 0.0.0.0:80 client/public/index.php | tee server.log
