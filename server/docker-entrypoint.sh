#!/usr/bin/env bash

echo "Running in $ENV env"

if [[ "$SERVER_MODE" -eq "1" ]]; then
    echo "App is in server mode: nginx and run php-fpm"
    service nginx start

    php-fpm
fi

echo "App is in harvester mode, use it:"
echo "docker-compose app run ..."
