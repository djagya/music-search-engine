<?php
declare(strict_types=1);

function serveStatic(string $clientPath): bool
{
    if (!preg_match('/\.(js|css|json|ico|html|php)$/i', $_SERVER["REQUEST_URI"], $m)) {
        return false;
    }

    $extension = $m[1];
    $file = $clientPath . $_SERVER["REQUEST_URI"];
    if (!file_exists($file)) {
        return false;
    }

    if ($extension === 'php') {
        require $file;
        return true;
    }

    $type = getMimeType($extension);
    if ($type) {
        header('Content-Type: ' . $type);
    }

    echo file_get_contents($file);

    return true;
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
