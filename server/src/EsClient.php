<?php

namespace app;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Exception;

class EsClient
{
    /**
     * @param bool $log
     * @return Client
     * @throws Exception when ES_HOST is not set
     */
    public static function build(bool $log = false): Client
    {
        $host = getenv('ES_HOST');
        if (!$host) {
            throw new Exception('ES_HOST env variable is unset');
        }

        $builder = ClientBuilder::create()
            ->setHosts([$host])
            ->setConnectionParams(['guzzleOptions' => ['curl.options' => [CURLOPT_CONNECTTIMEOUT => 2.0]]]);
        if ($log) {
            $builder->setTracer(Logger::get('es'));
        }

        return $builder->build();
    }
}
