# Use an official Python runtime as a parent image
FROM php:7.2-cli
RUN apt-get update && apt-get install -y mysql-client pv
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php -r "if (hash_file('sha384', 'composer-setup.php') === '48e3236262b34d30969dca3c37281b3b4bbe3221bda826ac6a9a62d6444cdb0dcd0615698a5cbe587c3f0fe57a54d8f5') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
RUN php composer-setup.php --install-dir=/usr/bin
RUN php -r "unlink('composer-setup.php');"

RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /app
COPY . /app

# Make port 80 available to the world outside this container
EXPOSE 80

ENV MYSQL_HOST 127.0.0.1
ENV DB_NAME music

CMD bash init.sh | tee init.log
