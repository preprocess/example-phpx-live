<?php

if (PHP_SAPI == "cli-server") {
    $url  = parse_url($_SERVER["REQUEST_URI"]);

    if (is_file(__DIR__ . $url["path"])) {
        return false;
    }
}

require_once __DIR__ . "/../vendor/autoload.php";

use Slim\App;
use function Pre\Plugin\process;

$app = new App();

process(__DIR__ . "/../source/helpers.pre");

$routes = process(__DIR__ . "/../source/routes.pre");
$routes($app);

$app->run();
