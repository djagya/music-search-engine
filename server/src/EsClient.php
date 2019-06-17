<?php

namespace app;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;

class EsClient
{
    public static function build(bool $log = false): Client
    {
        $host = getenv('ES_HOST');
        $builder = ClientBuilder::create()->setHosts([$host]);
        if ($log) {
            $builder->setTracer(Logger::get('es'));
        }

        return $builder->build();
    }
}
