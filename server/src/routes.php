<?php

use app\search\ChartSearch;
use app\search\RelatedSearch;
use app\search\TypingSearch;
use Aws\Ec2\Ec2Client;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * When typingResponse, autocomplete, show suggestions.
 */
$app->get('/api/typing', function (Request $request, Response $response) {
    $field = $request->getQueryParam('field');
    if (!$field) {
        throw new InvalidArgumentException('"field" url query param is required');
    }
    $query = $request->getQueryParam('query');
    if (!$query) {
        throw new InvalidArgumentException('"query" url query param is required');
    }
    $selected = json_decode($request->getQueryParam('selected', ''), true) ?: [];

    $search = new TypingSearch(
        (string) $field,
        $selected,
        (bool) $request->getQueryParam('meta', false),
        $request->getQueryParam('index')
    );
    $result = $search->search((string) $query);

    return $response->withJson($result);
});

/**
 * When a suggestions selected, search for relatedResponse documents for other fields.
 */
$app->get('/api/related', function (Request $request, Response $response, array $args) {
    $empty = explode(':', $request->getQueryParam('empty', ''));
    if (!$empty) {
        throw new InvalidArgumentException('"empty" url query param must contain at least one field');
    }
    $selected = json_decode($request->getQueryParam('selected', ''), true);
    if (!$selected) {
        throw new InvalidArgumentException('"selected" url query param must contain at least one field => value pair');
    }

    $search = new RelatedSearch(
        $empty,
        $selected,
        (bool) $request->getQueryParam('meta', false),
        $request->getQueryParam('index')
    );
    $result = $search->search();

    return $response->withJson($result);
});

/**
 * Chart application search API.
 */
$app->get('/api/chart', function (Request $request, Response $response) {
    $query = $request->getQueryParam('query', []);
    $type = $request->getQueryParam('type', ChartSearch::TYPE_SONGS);
    $chart = $request->getQueryParam('chart', false);

    $search = new ChartSearch($type, $chart, (bool) $request->getQueryParam('meta', false));
    $result = $search->search($query ?: [], $request->getQueryParams());

    return $response->withJson($result);
});

/**
 * todo: extract the instance id to the env variable
 * GET Instance status.
 * https://docs.aws.amazon.com/en_us/AWSEC2/latest/APIReference/API_DescribeInstanceStatus.html
 */
$app->get('/api/instance', function (Request $request, Response $response) {
    $client = getEc2Client();

    $res = $client->describeInstanceStatus(['InstanceId.1' => getenv('AWS_INSTANCE')]);
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
$app->post('/api/instance', function (Request $request, Response $response) {
    $start = $request->getParsedBodyParam('start', false);

    $client = getEc2Client();
    $body = ['InstanceIds' => ['InstanceId.1' => getenv('AWS_INSTANCE')],];

    $result = $start ? $client->startInstances($body) : $client->stopInstances($body);

    return $response->withJson($result->toArray());
});

function getEc2Client()
{
    return new Ec2Client([
        'region' => 'eu-central-1',
        'credentials' => ['key' => getenv('AWS_KEY'), 'secret' => getenv('AWS_SECRET')],
        'version' => 'latest',
    ]);
}
