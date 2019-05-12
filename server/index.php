<?php
declare(strict_types=1);

use Search\TypingSearch;
use Slim\Http\Request;
use Slim\Http\Response;

require 'vendor/autoload.php';
require 'src/static.php';

$clientPath = __DIR__ . '/../client/build';

// Fix the issue preventing the correct url path parsing.
if (PHP_SAPI == 'cli-server') {
    $_SERVER['SCRIPT_NAME'] = '/server.php';
}

$app = new Slim\App(['settings' => ['displayErrorDetails' => true]]);

/**
 * Serve static assets.
 */
$app->add(function (Request $request, Response $response, $next) use ($clientPath) {
    // Check if the requested file exists and can be served as a static asset.
    $static = serveStatic($clientPath);
    if (!$static) {
        return $next($request, $response);
    }

    return $response
        ->withHeader('Content-Type', $static[0])
        ->write($static[1]);
});

/**
 * When typing, autocomplete, show suggestions.
 */
$app->get('/typing', function (Request $request, Response $response) {
    $field = $request->getQueryParam('field');
    if (!$field) {
        throw new InvalidArgumentException('"field" url query param is required');
    }
    $query = $request->getQueryParam('query');
    if (!$query) {
        throw new InvalidArgumentException('"query" url query param is required');
    }

    $search = new TypingSearch((string)$field, (bool)$request->getQueryParam('meta', true));
    $result = $search->search((string)$query);

    return $response->withJson($result);
});

/**
 * When a suggestions selected, search for related documents for other fields.
 */
$app->get('/related', function (Request $request, Response $response, array $args) {
});

/**
 * Render the client react app.
 */
$app->get('/', function (Request $request, Response $response, array $args) use ($clientPath) {
    echo file_get_contents($clientPath . '/index.html');
});

$app->run();
