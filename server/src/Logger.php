<?php

namespace app;

use Exception;
use Monolog\Handler\StreamHandler;

/**
 * App logger helper utilizing Monolog.
 */
class Logger
{
    /** @var \Monolog\Logger[] */
    static private $loggers;

    public static function get(string $name = 'app'): \Monolog\Logger
    {
        if (empty(static::$loggers[$name])) {
            static::init($name);
        }

        return static::$loggers[$name];
    }

    /**
     * @throws Exception
     */
    protected static function init(string $name = 'app')
    {
        $logger = new \Monolog\Logger($name);

        $dest = __DIR__ . "/../../logs/$name.log";
        $level = getenv('ENV') === 'production' ? \Monolog\Logger::INFO : \Monolog\Logger::DEBUG;
        $logger->pushHandler(new StreamHandler($dest, $level));

        static::$loggers[$name] = $logger;
    }
}
