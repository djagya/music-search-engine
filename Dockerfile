FROM djagya/bachelor-search-base:latest

ENV MYSQL_HOST 127.0.0.1

WORKDIR /app

COPY configs/nginx.conf /etc/nginx/sites-available/default

COPY client/* /app/client/
COPY client/public /app/client/public
COPY client/src /app/client/src

COPY server/* /app/server/
COPY server/src /app/server/src

# Install PHP app packages
RUN php /usr/bin/composer.phar install --no-dev --no-interaction -o -d server

# Prepare client assets after we copied the /app, so npm won't load all package again.
RUN cd client && npm install  --production && npm run-script build

# Create volumes for package directories to not load them every time
VOLUME /app/client/node_modules
VOLUME /app/server/vendor
VOLUME /app/logs

CMD bash /app/server/docker-entrypoint.sh
