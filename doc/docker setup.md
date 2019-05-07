# Build
Build the image using the Dockerfile in the current directory, tag it as `app:latest`. It will replace the old image.
```bash
docker image build -t=app .
```

# Run
Then the image must be started.

To run the `app` image and make the `EXPOSE` port accessible on the host port `8080`:
```bash
docker run -p 8080:80 app
```

Development mode. Run the built container with its `WORKDIR` mounted to the `pwd` allowing to sync changed files with the container.
```bash
docker run -p 8080:80 -i -t -v `pwd`:/app app
```

To run the db server separately.
```bash
docker run -p 3306:3306 -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=music mariadb
```

To run only the main app via `docker-composer` and make the `client/build` available on `localhost:8080`.
```bash
docker-compose run -p 8080:80  app php -S 0.0.0.0:80 client/build/server
```

Client. Development mode with running `docker-compose up` and attached `./` volume.
```bash
npm run watch
```
