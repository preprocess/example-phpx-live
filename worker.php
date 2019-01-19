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
use function App\render;
use function Pre\Plugin\process;

process(__DIR__ . "/source/helpers.pre");

$websocket = new class extends Websocket
{
    private $roots = [];
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

        if (!isset($this->roots[$clientId])) {
            $this->roots[$clientId] = [];
        }

        if (!isset($this->binds[$clientId])) {
            $this->binds[$clientId] = [];
        }

        if ($json->type === "phpx-root") {
            print "Creating {$json->id} for {$clientId}" . PHP_EOL;

            $component = unserialize(base64_decode($json->root));

            $component->setId($json->id);
            $component->setSender($this->senders[$clientId]);

            $this->roots[$clientId][$json->id] = $component;

            $component->render();
            $component->componentDidMount();

            $class = get_class($component);

            print "Created {$json->id} ({$class}) for {$clientId}" . PHP_EOL;
        }

        if ($json->type === "phpx-click" || $json->type === "phpx-enter") {
            print "{$json->root}.{$json->method} triggered with {$json->type} from {$clientId}" . PHP_EOL;

            $binds = isset($this->binds[$clientId][$json->root])
                ? $this->binds[$clientId][$json->root]
                : [];

            $arguments = !empty($json->arguments)
                ? explode(",", (string) $json->arguments)
                : [];

            $object = $this->roots[$clientId][$json->root];
            $object->{$json->method}($binds, ...$arguments);

            $data = json_encode([
                "cause" => $json->type,
                "type" => "phpx-render",
                "data" => (string) $object->statefulRender(),
                "root" => $json->root,
            ]);

            $this->send($data, $clientId);
        }

        if ($json->type === "phpx-bind") {
            if (!isset($this->binds[$clientId][$json->root])) {
                $this->binds[$clientId][$json->root] = [];
            }

            $this->binds[$clientId][$json->root][$json->key] = $json->value;
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
