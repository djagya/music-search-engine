<?php

namespace Search;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;

class EsClient
{
    public static function build(): Client
    {
        $host = getenv('ES_HOST');

        return ClientBuilder::create()
            ->setHosts([$host])
            ->build();
    }
}
