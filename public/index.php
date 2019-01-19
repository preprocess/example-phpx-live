<?php

if (PHP_SAPI == "cli-server") {
    $url  = parse_url($_SERVER["REQUEST_URI"]);

    if (is_file(__DIR__ . $url["path"])) {
        return false;
    }
}

require_once __DIR__ . "/../vendor/autoload.php";

$app = new Slim\App();

Pre\Plugin\process(__DIR__ . "/../source/helpers.pre");

$routes = Pre\Plugin\process(__DIR__ . "/../source/routes.pre");
$routes($app);

$app->run();
