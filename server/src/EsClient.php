<?php

namespace Search;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;

class EsClient
{
    public static function build(): Client
    {
        return ClientBuilder::create()
//    ->setHosts(['es01', 'es02']) // in development only es01 is available
            ->setHosts(['es01'])
            ->build();
    }
}
