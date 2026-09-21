<?php
// Local (XAMPP) config. On Mijndomein, replace these with the Plesk-provided
// DB credentials and adjust the storage paths to a directory outside the
// public webroot (see CLAUDE.md "File storage").

return [
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
