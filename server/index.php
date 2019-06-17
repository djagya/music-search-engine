<?php
declare(strict_types=1);

use app\ErrorHandler;
use Slim\Http\Request;
use Slim\Http\Response;

require 'vendor/autoload.php';

$clientPath = __DIR__ . '/../client/build';

// Fix the issue preventing the correct url path parsing.
if (PHP_SAPI == 'cli-server') {
    $_SERVER['SCRIPT_NAME'] = '/server.php';
}

ErrorHandler::register();
$app = new Slim\App([
    'settings' => [
        //'displayErrorDetails' => getenv('ENV') !== 'production',
        'displayErrorDetails' => true,
    ],
    'errorHandler' => function ($container) {
        return ErrorHandler::errorHandler($container);
    },
    'phpErrorHandler' => function ($container) {
        return ErrorHandler::phpErrorHandler($container);
    },
]);

/**
 * Render the client react app.
 */
$app->get('/', function (Request $request, Response $response, array $args) use ($clientPath) {
    echo file_get_contents($clientPath . '/index.html');
});

require 'src/routes.php';

$app->run();
