<?php
// Router for `php -S` (local development only). Mirrors the EspoCRM nginx/apache
// rules: docroot = public/, /client/ served from ../client/, API -> public/api/v1.
// Production must use nginx/apache — see docs/DEPLOYMENT.md.

$root = getcwd(); // instance root (serve.sh runs php -S from there)
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (str_starts_with($path, '/client/')) {
    $file = realpath($root . $path);

    if (!$file || !str_starts_with($file, $root . '/client/') || !is_file($file)) {
        http_response_code(404);
        return true;
    }

    $types = ['js' => 'application/javascript', 'css' => 'text/css', 'json' => 'application/json',
        'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'woff2' => 'font/woff2',
        'woff' => 'font/woff', 'ttf' => 'font/ttf', 'tpl' => 'text/html', 'csv' => 'text/csv',
        'map' => 'application/json', 'ico' => 'image/x-icon', 'gif' => 'image/gif'];
    header('Content-Type: ' . ($types[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
    readfile($file);

    return true;
}

if (str_starts_with($path, '/api/v1/')) {
    $_SERVER['SCRIPT_NAME'] = '/api/v1/index.php';
    chdir($root . '/public/api/v1');
    require 'index.php';

    return true;
}

if ($path === '/' || $path === '/index.php') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    chdir($root . '/public');
    require 'index.php';

    return true;
}

http_response_code(404);

return true;
