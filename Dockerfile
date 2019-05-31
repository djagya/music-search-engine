# Use an official Python runtime as a parent image
FROM php:7.2-cli

ENV MYSQL_HOST 127.0.0.1
ENV DB_NAME music
# Make port 80 available to the world outside this container
EXPOSE 80

# Nodejs repo
RUN curl -sL https://deb.nodesource.com/setup_12.x | bash -

RUN apt-get update && apt-get install -y mysql-client nodejs git
RUN docker-php-ext-install pdo pdo_mysql pcntl

# PHP config
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN sed -i -e 's/memory_limit = 128M/memory_limit = 1G/g' "$PHP_INI_DIR/php.ini"

# Install composer and packages
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php -r "if (hash_file('sha384', 'composer-setup.php') === '48e3236262b34d30969dca3c37281b3b4bbe3221bda826ac6a9a62d6444cdb0dcd0615698a5cbe587c3f0fe57a54d8f5') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
RUN php composer-setup.php --install-dir=/usr/bin
RUN php -r "unlink('composer-setup.php');"

WORKDIR /app
COPY init.sh /app
COPY client /app/client
COPY server /app/server
COPY logs /app/logs

# Install PHP app packages
RUN php /usr/bin/composer.phar install --no-dev --no-interaction -o -d server

# Prepare client assets after we copied the /app, so npm won't load all package again.
RUN cd client && npm install && npm rebuild node-sass && npm run-script build

CMD bash init.sh
