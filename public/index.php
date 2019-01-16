<?php

if (PHP_SAPI == "cli-server") {
    $url  = parse_url($_SERVER["REQUEST_URI"]);

    if (is_file(__DIR__ . $url["path"])) {
        return false;
    }
}

require __DIR__ . "/../vendor/autoload.php";

use App\CombinedResponder;
use App\CounterResponder;
use App\HomeResponder;
use App\TimeResponder;
use App\TodosResponder;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\App;

\Pre\Plugin\process(__DIR__ . "/../source/helpers.pre");

$app = new App();

$app->get("/", function(Request $request, Response $response) {
    $response->getBody()->write((new HomeResponder())->render());
    return $response;
});

$app->get("/counter", function(Request $request, Response $response) {
    $response->getBody()->write((new CounterResponder())->render());
    return $response;
});

$app->get("/todos", function(Request $request, Response $response) {
    $response->getBody()->write((new TodosResponder())->render());
    return $response;
});

$app->get("/combined", function(Request $request, Response $response) {
    $response->getBody()->write((new CombinedResponder())->render());
    return $response;
});

$app->get("/time", function(Request $request, Response $response) {
    $response->getBody()->write((new TimeResponder())->render());
    return $response;
});

$app->run();
