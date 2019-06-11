<?php

namespace Search;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;

class EsClient
{
    public static function build(): Client
    {
        return ClientBuilder::create()
            ->setHosts([getenv('ES_HOST')])
            ->build();
    }
}
