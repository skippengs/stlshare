<?php
// Base config with local (XAMPP) defaults. Environment-specific values (real
// DB credentials on Mijndomein) live in config.local.php instead of here —
// that file is gitignored and is written automatically by public/install.php
// (or copy config.local.php.example and fill it in by hand).

$defaults = [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'stlsharer',
        'user' => 'root',
        'pass' => '',
    ],
    'storage' => [
        // Outside public/ so files are only reachable through the gated
        // download routes in index.php, never by guessing a URL.
        'models_dir'     => dirname(__DIR__) . '/storage/models',
        'thumbnails_dir' => dirname(__DIR__) . '/storage/thumbnails',
    ],
    'upload' => [
        'max_bytes' => 100 * 1024 * 1024, // 100 MB per file, per plan.md
    ],
    'app' => [
        'name' => 'STL Sharer',
    ],
];

$localFile = __DIR__ . '/config.local.php';
if (is_file($localFile)) {
    $local = require $localFile;
    return array_replace_recursive($defaults, is_array($local) ? $local : []);
}

return $defaults;
