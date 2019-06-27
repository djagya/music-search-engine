<?php

namespace app;

use Psr\Log\LogLevel;
use Slim\Handlers\Error;
use Slim\Handlers\PhpError;
use Throwable;

/**
 * Custom Slim error handler. Writes errors to the standard app log.
 */
class ErrorHandler
{
    public static function register()
    {
        \Monolog\ErrorHandler::register(Logger::get());
    }

    public static function errorHandler($container)
    {
        $handler = new Error($container->get('settings')['displayErrorDetails']);

        return function ($request, $response, $error) use ($handler) {
            static::logException($error);

            return $handler->__invoke($request, $response, $error);
        };
    }

    public static function phpErrorHandler($container)
    {
        $handler = new PhpError($container->get('settings')['displayErrorDetails']);

        return function ($request, $response, $error) use ($handler) {
            static::logException($error);

            return $handler->__invoke($request, $response, $error);
        };
    }

    protected static function logException(Throwable $e)
    {
        Logger::get()->log(
            LogLevel::ERROR,
            sprintf('Uncaught Exception %s: "%s" at %s line %s', get_class($e), $e->getMessage(), $e->getFile(),
                $e->getLine()),
            ['exception' => $e]
        );
    }
}
