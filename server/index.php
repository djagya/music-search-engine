<?php
declare(strict_types=1);

use Aws\Ec2\Ec2Client;
use Search\RelatedSearch;
use Search\TypingSearch;
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
        'displayErrorDetails' => true,
        'logger' => [
            'name' => 'slim-app',
            'level' => Monolog\Logger::INFO,
            'path' => __DIR__ . '/../logs/app.log',
        ],
    ]
]);

/**
 * When typingResponse, autocomplete, show suggestions.
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
    $selected = json_decode($request->getQueryParam('selected', ''), true) ?: [];

    $search = new TypingSearch((string)$field, $selected, (bool)$request->getQueryParam('meta', true));
    $result = $search->search((string)$query);

    return $response->withJson($result);
});

/**
 * When a suggestions selected, search for relatedResponse documents for other fields.
 */
$app->get('/related', function (Request $request, Response $response, array $args) {
    $empty = explode(':', $request->getQueryParam('empty', ''));
    if (!$empty) {
        throw new InvalidArgumentException('"empty" url query param must contain at least one field');
    }
    $selected = json_decode($request->getQueryParam('selected', ''), true);
    if (!$selected) {
        throw new InvalidArgumentException('"selected" url query param must contain at least one field => value pair');
    }

    $search = new RelatedSearch($empty, $selected, (bool)$request->getQueryParam('meta', true));
    $result = $search->search();

    return $response->withJson($result);
});

/**
 * GET Instance status.
 * https://docs.aws.amazon.com/en_us/AWSEC2/latest/APIReference/API_DescribeInstanceStatus.html
 */
$app->get('/instance', function (Request $request, Response $response) {
    $client = new Ec2Client([
        'region' => 'eu-central-1',
        'credentials' => ['key' => 'AKIARDAFFXQCAXG3XDMG', 'secret' => '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'],
        'version' => 'latest',
    ]);
    $res = $client->describeInstanceStatus([
        'InstanceId.1' => 'i-0944a300ec006cf83',
    ]);

    $body = [
        'running' => ($res->toArray()['InstanceStatuses'][0]['InstanceState']['Code'] ?? null) === 16,
        'response' => $res->toArray(),
    ];

    return $response->withJson($body);
});

/**
 * Start/stop the instances.
 * https://docs.aws.amazon.com/en_us/AWSEC2/latest/APIReference/API_StartInstances.html
 */
$app->post('/instance', function (Request $request, Response $response) {
    $start = $request->getParsedBodyParam('start', false);
    $client = new Ec2Client([
        'region' => 'eu-central-1',
        'credentials' => ['key' => 'AKIARDAFFXQCAXG3XDMG', 'secret' => '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'],
        'version' => 'latest',
    ]);

    $body = [
        'InstanceIds' => ['InstanceId.1' => 'i-0944a300ec006cf83'],
    ];

    if ($start) {
        $result = $client->startInstances($body);
    } else {
        $result = $client->stopInstances($body);
    }

    return $response->withJson($result->toArray());
});

/**
 * Render the client react app.
 */
$app->get('/', function (Request $request, Response $response, array $args) use ($clientPath) {
    echo file_get_contents($clientPath . '/index.html');
});

$app->run();
