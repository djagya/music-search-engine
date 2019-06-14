<?php
declare(strict_types=1);

use Slim\Http\Request;
use Slim\Http\Response;

require 'vendor/autoload.php';

$clientPath = __DIR__ . '/../client/build';

// Fix the issue preventing the correct url path parsing.
if (PHP_SAPI == 'cli-server') {
    $_SERVER['SCRIPT_NAME'] = '/server.php';
}

$app = new Slim\App([
    'settings' => [
        'displayErrorDetails' => getenv('ENV') !== 'production',
        'logger' => [
            'name' => 'slim-app',
            'level' => Monolog\Logger::INFO,
            'path' => __DIR__ . '/../logs/app.log',
        ],
    ]
]);

/**
 * Render the client react app.
 */
$app->get('/', function (Request $request, Response $response, array $args) use ($clientPath) {
    echo file_get_contents($clientPath . '/index.html');
});

require 'src/routes.php';

$app->run();
