<?php
declare(strict_types=1);

$publicPath = __DIR__ . '/build/';

$file = $publicPath . '/index.html';
if (preg_match('/\.(js|css|json|ico|html|php)$/i', $_SERVER["REQUEST_URI"], $m)) {
    $extension = $m[1];
    $file = $publicPath . $_SERVER["REQUEST_URI"];
    if ($extension === 'php') {
        require $file;
        return;
    }

    $type = getMimeType($extension);
    if ($type) {
        header('Content-Type: ' . $type);
    }

}

echo file_get_contents($file);

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
