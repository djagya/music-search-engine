<?php

namespace Search;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;

class EsClient
{
    public static function build(): Client
    {
        $host = getenv('ES_HOST');

        echo "Elastic Search host: $host\n";

        return ClientBuilder::create()
            ->setHosts([$host])
            ->build();
    }
}
