<?php
declare(strict_types=1);

session_start();

spl_autoload_register(function (string $class): void {
    $path = __DIR__ . '/../src/lib/' . $class . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require __DIR__ . '/../src/lib/helpers.php';

foreach (glob(__DIR__ . '/../src/controllers/*.php') as $controllerFile) {
    require $controllerFile;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

// Strip the app's base path so routes work whether it's deployed at a domain
// root (Mijndomein httpdocs/) or, as in local XAMPP testing, under a
// subdirectory like /stlsharer/.
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($scriptDir !== '' && str_starts_with($path, $scriptDir)) {
    $path = substr($path, strlen($scriptDir));
}

$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}

/** @var array<int, array{0:string,1:string,2:callable}> $routes */
$routes = [
    ['GET', '#^/$#', 'library_index'],
    ['GET', '#^/register$#', 'auth_register_form'],
    ['POST', '#^/register$#', 'auth_register_submit'],
    ['GET', '#^/login$#', 'auth_login_form'],
    ['POST', '#^/login$#', 'auth_login_submit'],
    ['POST', '#^/logout$#', 'auth_logout'],
    ['GET', '#^/pending$#', 'auth_pending'],
    ['GET', '#^/privacy$#', 'legal_privacy'],
    ['GET', '#^/terms$#', 'legal_terms'],

    ['GET', '#^/upload$#', 'models_upload_form'],
    ['POST', '#^/upload$#', 'models_upload_submit'],
    ['GET', '#^/models/(\d+)$#', 'models_show'],
    ['GET', '#^/models/(\d+)/stl$#', 'models_stream_stl'],
    ['GET', '#^/models/(\d+)/thumb$#', 'models_stream_thumb'],
    ['POST', '#^/models/(\d+)/thumbnail$#', 'models_save_thumbnail'],
    ['POST', '#^/models/(\d+)/delete$#', 'models_delete'],
    ['POST', '#^/models/(\d+)/favorite$#', 'favorites_toggle'],

    ['GET', '#^/favorites$#', 'favorites_index'],

    ['GET', '#^/collections$#', 'collections_index'],
    ['POST', '#^/collections$#', 'collections_create'],
    ['GET', '#^/collections/(\d+)$#', 'collections_show'],
    ['POST', '#^/collections/(\d+)/delete$#', 'collections_delete'],
    ['POST', '#^/collections/(\d+)/add$#', 'collections_add_model'],
    ['POST', '#^/collections/(\d+)/remove$#', 'collections_remove_model'],

    ['GET', '#^/account$#', 'account_show'],
    ['POST', '#^/account/delete$#', 'account_delete'],

    ['GET', '#^/admin$#', 'admin_dashboard'],
    ['POST', '#^/admin/users/(\d+)/approve$#', 'admin_user_approve'],
    ['POST', '#^/admin/users/(\d+)/reject$#', 'admin_user_reject'],
    ['POST', '#^/admin/users/(\d+)/suspend$#', 'admin_user_suspend'],
    ['POST', '#^/admin/users/(\d+)/reinstate$#', 'admin_user_reinstate'],
    ['POST', '#^/admin/users/(\d+)/quota$#', 'admin_user_quota'],
];

foreach ($routes as [$routeMethod, $pattern, $handler]) {
    if ($routeMethod !== $method) {
        continue;
    }
    if (preg_match($pattern, $path, $matches)) {
        array_shift($matches);
        $handler(...$matches);
        return;
    }
}

http_response_code(404);
render('errors/404', ['pageTitle' => 'Not found']);
