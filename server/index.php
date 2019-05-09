<?php
declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Fix the issue preventing the correct url path parsing.
if (PHP_SAPI == 'cli-server') {
    $_SERVER['SCRIPT_NAME'] = '/server.php';
}

require 'vendor/autoload.php';
require 'src/static.php';

// Check if the requested file exists and can be served.
$clientPath = __DIR__ . '/../client/build';
if (serveStatic($clientPath)) {
    return;
}

$app = new Slim\App();

$app->get('/search', function (Request $request, Response $response, array $args) {
    var_dump($request->getQueryParams());
});

//$app->get('/hello/{name}', function ($request, $response, $args) {
//    return $response->getBody()->write("Hello, " . $args['name']);
//});

// React app.
$app->get('/', function (Request $request, Response $response, array $args) use ($clientPath) {
    echo file_get_contents($clientPath . '/index.html');
});

$app->run();
