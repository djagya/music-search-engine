FROM djagya/bachelor-search-base:latest

ENV MYSQL_HOST 127.0.0.1

WORKDIR /app
COPY client /app/client
COPY server /app/server
COPY logs /app/logs
COPY configs/nginx.conf /etc/nginx/sites-available/default

# Install PHP app packages
RUN php /usr/bin/composer.phar install --no-dev --no-interaction -o -d server

# Prepare client assets after we copied the /app, so npm won't load all package again.
RUN cd client && npm install && npm rebuild node-sass && npm run-script build

CMD bash /app/server/docker-entrypoint.sh
