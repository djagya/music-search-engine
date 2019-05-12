<?php
declare(strict_types=1);

function serveStatic(string $clientPath): ?array
{
    if (!preg_match('/\.(js|css|json|ico|html)$/i', $_SERVER["REQUEST_URI"], $m)) {
        return null;
    }

    $extension = $m[1];
    $file = $clientPath . $_SERVER["REQUEST_URI"];
    if (!file_exists($file)) {
        return null;
    }

    return [getMimeType($extension), file_get_contents($file)];
}

function getMimeType(string $extension): ?string
{
    $types = [
        'txt' => 'text/plain',
        'html' => 'text/html',
        'php' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'swf' => 'application/x-shockwave-flash',
        'flv' => 'video/x-flv',
    ];

    return $types[$extension] ?? null;
}
