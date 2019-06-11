# Overview
This application demonstrates an integration of multiple services to provide a quick and precise search over a big amount of relational data.
This includes:
- usage of data relations information to narrow the result dataset,
- search by a prefix match `query*` to provide a list of autocomplete suggestions during the data enter,
- access to other non-indexed data fields to provide the needed metadata for the list of matched found results.
 
The **tech stack** consists of:
- **Elastic Search**, search engine
- **MariaDB**, database where the original data are stored for indexing purposes
- **PHP**, web-server and utility scripts
- **ReactJS**, web application framework
- **Docker**, deployment tool

The application consist of multiple Docker containers:
- `es01` is the Elastic Search container
- `db` is the MariaDB container, stores ES index data sources
- `app` is the main application with the server and client code

These containers can be run on the same host machine using `docker-compose`. 
But in **production** they are deployed on two different servers: 
- one is the high-performance EC2 instance, that is turned on only when required due to its cost, where `es01`, `db` and `app` (in **harvest mode**) are deployed
- second is a regular server where the `app` is deployed in **server mode** providing access to the web application and API

Below is the description of both servers deployments.

## The Data server

The data server is hosted on a powerful EC2 AWS instance.

The Docker Compose config file `docker-compose.yml` orchestrates multiple containers.
They are connected using the common `mainnet` network.

#### Start

To work with the data the services must be started first.
```bash
docker-compose # to start all services
```

Once they are running, the data and indexes can be accessed and modified.  
If the application is launched for the first time, it doesn't have any source data or indexed data.

#### Load data

There are two sources of data for search indexes:
- `spins` data source is the data of Spinitron application entered by customers, represent song plays. 
    Denormalized by default, so there are no relations between table rows.
- `epf` data source is the Apple Music data that represents musical artists, releases, songs and corresponding metadata. 
    Artist-release-song relations are described in pivot tables. 
    The data will be denormalized for index purposes.


To ingest the database and index it them the following steps are required (with the project root as a current working directory):

1. Download the data dumps to the `data` directory (TODO: provide links to hosted data dumps of spins and EPF somewhere). **Careful, the file size is pretty big**
    ```bash
    # EPF database dump
    curl -o data/epf-myisam-tables.tgz ...
    
    # Spins data dump
    curl -o data/spins.sql.gz ...
    ```

2. Load the data to the database
    ```bash
    # EPF data source
    docker-compose exec db bash /root/data/load.sh epf
    
    # Spins data source
    docker-compose exec db bash /root/data/load.sh spins
    ```

3. Harvest and index the data
    ```bash
    # EPF data source
    docker-compose exec app php server/harvest.php epf
    
    # Spins data source
    docker-compose exec app php server/harvest.php spins
    ```

Once these steps are finished, the `db` container should have two databases: `music` and `epf` for "spins" and "EPF" data sources respectively.  
There should be two indexes available in the `es01` container: `spins` (indexed spins data source) and `music` (indexed denormalized EPF data source).


### `es01` Elastic Search container

This is where the Elastic Search instance is hosted. 

TODO: describe memory variables, mounted volume, 

### `db` MariaDB container

TODO: describe memory variables, mounted volume, 


## The App server

The client server consists of single `app` docker container in **server** mode (started with `RUN_SERVER=1` env variable).  
In server mode the local PHP server is started on the container port `80` and provides access to the search API (see below) and to the client app.

#### Production mode
Web-server will be available on the port `8080`. 
The database and Elastic Search host environment variables must be specified. 
In production deployment they would be equal the EC2 instance private IP where the *data server* is hosted.

```bash
docker run -p 8080:80 -e MYSQL_HOST=... -e ES_HOST=... app
```


#### Development mode
To build the `app` container:

```bash
docker image build -t=app .
```

To start the server in development mode with the mounted current directory:

```bash
docker run -p 8080:80 -i -t -v `pwd`:/app app
```

Mounting the current directory allows to sync file changes between the host machine and container.
It's useful for immediate asset files update when running the client side watcher `cd client && npm start`.

#### Environment variables

There are multiple env variables the `app` container accepts:

- `MYSQL_HOST` - where the music and epf databases are hosted
- `ES_HOST` - where the Elastic Search instance is hosted
- `CHECK_DB` - should the `app` entry script check and create missing databases on start?
- `RUN_SERVER` - should the `app` entry script start the PHP server?

#### App server API

There are two main endpoint:

##### `GET /typing?field=...&query=...` 
Get autocomplete suggestions for the specified `field` based on the given `query`.
It is requested on every autocomplete input field change.

Accepted params:
- `field` string, the field name to get autocomplete suggestions for
- `query` string, the user query to search suggestions by
- `selected` optional json-encoded string, list of other already chosen fields to limit the suggestions by. Format: `{fieldName: 'value', ...}`
- `meta=1` optional bool, turn on to return the full Elastic Search response data. When turned off (`0`), only the list of formatted suggestions in format expected by the client app is returned.

##### `GET /related?empty=...&selected=...`
Get a list of `empty` fields suggestions related to the `selected` fields values.
It is requested on an autocomplete suggestion selection that leads to the value *choosing* of a field.

Accepted params:
- `empty` string, the list of the empty fields to get suggestions for separated by `:`. Format: `fieldName1:fieldName2`
- `selected` optional json-encoded string, list of other already chosen fields to limit the suggestions by. Format: `{fieldName: 'value', ...}`
- `meta=1` optional bool, turn on to return the full Elastic Search response data. When turned off (`0`), only the list of formatted suggestions in format expected by the client app is returned.









**To check memory/cpu usage stats:** `docker stats`





# Build
Build the image using the Dockerfile in the current directory, tag it as `app:latest`. It will replace the old image.
```bash
docker image build -t=app .
```

However most of the time `docker-compose` should be used as it manages all required services: db, ES nodes, the app.  
```bash
docker-compose up
```

To start the stack also rebuilding the `app` image:
```bash
docker-compose up --build
```

# Load data
To load a data dump to the db put the gzipped dump in `./data`, load the dump:
```bash
docker-compose exec db bash /root/data/load.sh
```

To drop the indexes, configure and ingest them again:
```bash
docker-compose exec app php server/harvest.php
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

To run only the main app via `docker-compose` and make the `client/build` available on `localhost:8080`.
```bash
docker-compose run -p 8080:80  app php -S 0.0.0.0:80 client/build/server
```

Client. Development mode with running `docker-compose up` and attached `./` volume, that overrides the built assets from the docker image.
```bash
npm run start
```
