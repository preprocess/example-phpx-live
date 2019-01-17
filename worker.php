<?php

define("PHPX_LIVE_IN_WORKER", true);

require_once __DIR__ . "/vendor/autoload.php";

use Amp\Http\Server\Options;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Http\Server\Server;
use Amp\Http\Server\Websocket\Message;
use Amp\Http\Server\Websocket\Websocket;
use Amp\Loop;
use Amp\Socket;
use App\Updates;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

\Pre\Plugin\process(__DIR__ . "/source/helpers.pre");

$websocket = new class extends Websocket
{
    private $classes = [];
    private $binds = [];
    private $senders = [];

    public function onHandshake(Request $request, Response $response)
    {
        // authenticate this connection...

        return $response;
    }

    public function onOpen(int $clientId, Request $request)
    {
        $this->senders[$clientId] = function($data) use ($clientId) {
            $this->send($data, $clientId);
        };
    }

    public function onData(int $clientId, Message $message)
    {
        $text = yield $message->buffer();
        $json = json_decode($text);

        if (!isset($this->classes[$clientId])) {
            $this->classes[$clientId] = [];
        }

        if (!isset($this->binds[$clientId])) {
            $this->binds[$clientId] = [];
        }

        if ($json->type === "phpx-init") {
            foreach ($json->classes as $class) {
                if (class_exists($class)) {
                    $this->classes[$clientId][$class] = new $class();

                    if (method_exists($this->classes[$clientId][$class] , "setSender")) {
                        $this->classes[$clientId][$class]->setSender($this->senders[$clientId]);
                    }

                    if (method_exists($this->classes[$clientId][$class], "componentDidMount")) {
                        $this->classes[$clientId][$class]->componentDidMount();
                    }

                    print "Creating {$class} for {$clientId}" . PHP_EOL;
                }
            }
        }

        if ($json->type === "phpx-click" || $json->type === "phpx-enter") {
            print "{$json->class}.{$json->method} triggered with {$json->type} from {$clientId}" . PHP_EOL;

            $binds = isset($this->binds[$clientId][$json->class])
                ? $this->binds[$clientId][$json->class]
                : [];

            $arguments = !empty($json->arguments)
                ? explode(",", (string) $json->arguments)
                : [];

            $this->classes[$clientId][$json->class]->{$json->method}($binds, ...$arguments);

            $data = json_encode([
                "cause" => $json->type,
                "type" => "phpx-render",
                "data" => (string) $this->classes[$clientId][$json->class]->render(),
                "class" => $json->class,
                "id" => $json->id,
            ]);

            $this->send($data, $clientId);
        }

        if ($json->type === "phpx-bind") {
            // print "{$json->class}.{$json->key} = '{$json->value}' from {$clientId}" . PHP_EOL;

            if (!isset($this->binds[$clientId][$json->class])) {
                $this->binds[$clientId][$json->class] = [];
            }

            $this->binds[$clientId][$json->class][$json->key] = $json->value;
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

    print "Starting the worker" . PHP_EOL;
});
