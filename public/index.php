<?php

declare(strict_types=1);

/**
 * Framework Title: Natti-X
 * Author: Celio Natti
 * Copyright: X, 2024
 * Version: 1.0.0
 */

use Dotenv\Dotenv;
use X\X;

require dirname(__DIR__) . "/vendor/autoload.php";

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$x = new X();

$x_app = [];
$x_actions = [];
$x_filters = [];
$x_data = [];

require $x->pathResolver->routesPath() . DIRECTORY_SEPARATOR . "web.php";
$pluginRoutes = $x->pathResolver->pluginRoutes();

if ($pluginRoutes !== null) {
    foreach ($pluginRoutes as $pluginRoute) {
        require $pluginRoute;
    }
}

try {
    $x->run();
} catch (Throwable $e) {
}