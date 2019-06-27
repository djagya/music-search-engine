# Overview
This application demonstrates an integration of multiple services to provide a quick and precise search over a big amount of relational data.
This includes:
- usage of data relations information to narrow the result dataset,
- search by a prefix match to provide a list of autocomplete suggestions during the data enter,
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
docker-compose up
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
    docker-compose exec db bash data/load.sh epf
    
    # Spins data source
    docker-compose exec db bash data/load.sh spins
    ```

3. Harvest and index the data with 6 forks (one per CPU)
    ```bash
    # EPF data source
    docker-compose run app php server/harvest.php epf 6
    
    # Spins data source
    docker-compose run app php server/harvest.php spins 6
    ```

Once these steps are finished, the `db` container should have two databases: `spins` and `epf` for "spins" and "EPF" data sources respectively.  
There should be two indexes available in the `es01` container: `spins` (indexed spins data source) and `music` (indexed denormalized EPF data source).


## The App server

The client server consists of single `app` docker container in **server** mode (started with `SERVER_MODE=1` env variable).  
In server mode the local PHP server is started on the container port `80` and provides access to the search API (see below) and to the client app.

#### Environment variables

There are multiple env variables the `app` container accepts:

- `MYSQL_HOST` - where the spins and epf databases are hosted
- `ES_HOST` - where the Elastic Search instance is hosted
- `SERVER_MODE` - should the `app` entry script start the PHP server?

To update env variables in containers:

```bash
docker-compose up -d
```

#### Log files

App logs are located in the `/app/logs` directory. There are few available log files:

- `logs/app.log` main PHP app log
- `logs/nginx.error.log` nginx error log
- `logs/search.log` all application search related information
- `logs/es.log` ElasticSearch trace logs

To watch a logfile in real-time:

```bash
docker-compose exec app tail -f logs/search.log
```


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

To warm ES data run in es01 and es02 container:
```bash
yum install git make sudo gcc
git clone https://github.com/hoytech/vmtouch.git
cd vmtouch
make
sudo make install
```
