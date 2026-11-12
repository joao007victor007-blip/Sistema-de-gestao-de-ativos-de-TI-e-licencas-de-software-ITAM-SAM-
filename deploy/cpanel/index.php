<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Produção (cPanel): o sistema fica fora do public_html, em /home/usuario/intranet-app
$appPath = __DIR__ . '/../../intranet-app';

if (file_exists($maintenance = $appPath . '/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appPath . '/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appPath . '/bootstrap/app.php';

// Informa ao Laravel que a pasta pública é esta (onde estão o CSS e o JS compilados)
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());