<?php

require __DIR__ . "/vendor/autoload.php";

use Amp\Http\Server\Options;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Http\Server\Server;
use Amp\Http\Server\Websocket\Message;
use Amp\Http\Server\Websocket\Websocket;
use Amp\Loop;
use Amp\Socket;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

\Pre\Plugin\process(__DIR__ . "/source/helpers.pre");

$websocket = new class extends Websocket
{
    private $instances = [];
    private $bindings = [];

    public function onHandshake(Request $request, Response $response)
    {
        // authenticate this connection...

        return $response;
    }

    public function onOpen(int $clientId, Request $request)
    {
        // do nothing
    }

    public function onData(int $clientId, Message $message)
    {
        $text = yield $message->buffer();
        $json = json_decode($text);

        if ($json->type === "phpx-init") {
            foreach ($json->classes as $class) {
                if (class_exists($class)) {
                    $this->instances[$class] = new $class();
                    print "Creating {$class} for {$clientId}" . PHP_EOL;
                }
            }
        }

        if ($json->type === "phpx-click" || $json->type === "phpx-enter") {
            print "{$json->class}.{$json->method} triggered with {$json->type} from {$clientId}" . PHP_EOL;

            $bindings = isset($this->bindings[$json->class])
                ? $this->bindings[$json->class]
                : [];

            $arguments = !empty($json->arguments)
                ? explode(",", (string) $json->arguments)
                : [];

            $this->instances[$json->class]->{$json->method}($bindings, ...$arguments);

            $data = json_encode([
                "cause" => $json->type,
                "type" => "phpx-render",
                "data" => (string) $this->instances[$json->class]->render(),
                "class" => $json->class,
                "id" => $json->id,
            ]);

            yield $this->send($data, $clientId);
        }

        if ($json->type === "phpx-bind") {
            print "{$json->class}.{$json->key} = '{$json->value}' from {$clientId}" . PHP_EOL;

            if (!isset($this->bindings[$json->class])) {
                $this->bindings[$json->class] = [];
            }

            $this->bindings[$json->class][$json->key] = $json->value;
        }
    }

    public function onClose(int $clientId, int $code, string $reason)
    {
        // do nothing
    }
};

$sockets = [
    Socket\listen("127.0.0.1:8889"),
];

$router = new Router();
$router->addRoute("GET", "/", $websocket);

if (file_exists(__DIR__ . "/worker.log")) {
    unlink(__DIR__ . "/worker.log");
}

$logger = new Logger("phpx-live");
$logger->pushHandler(new StreamHandler(__DIR__ . "/worker.log", Logger::WARNING));

$options = (new Options())->withDebugMode()->withHttp2Upgrade();

$server = new Server($sockets, $router, $logger, $options);

Loop::run(function() use ($server) {
    yield $server->start();

    print "Server listening" . PHP_EOL;
});
