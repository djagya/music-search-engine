FROM djagya/bachelor-search-base:latest

ENV MYSQL_HOST 127.0.0.1

WORKDIR /app

COPY configs/nginx.conf /etc/nginx/sites-available/default
COPY client /app/client
COPY server /app/server

# Install PHP and js app packages
RUN php /usr/bin/composer.phar install --no-dev --no-interaction -o -d server
RUN cd client && npm install rebuild node-sass && npm install --production && npm run-script build

CMD bash /app/server/docker-entrypoint.sh
