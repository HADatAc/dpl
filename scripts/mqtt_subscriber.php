<?php

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;
use Drupal\dpl\Service\MqttService;

use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;


require_once '/opt/drupal/web/autoload.php';
require_once '/opt/drupal/vendor/autoload.php';

$kernel = DrupalKernel::createFromRequest(Request::createFromGlobals(), $autoloader, 'prod');
$kernel->boot();

$options = getopt('', ['ip:', 'port:', 'topics:', 'ws-port:']);
$ip = $options['ip'];
$port = $options['port'];
$topics = explode(',', $options['topics']);
$wsPort = $options['ws-port'] ?? 8082;
$httpPort = 8081;

class MqttWebSocket implements MessageComponentInterface {
  protected $clients;
  protected $subscriptions; // cliente => array de tópicos


  public function __construct() {
    $this->clients = new \SplObjectStorage;
    $this->subscriptions = [];

  }

  public function onOpen(ConnectionInterface $conn) {
    $this->clients->attach($conn);
    $this->subscriptions[$conn->resourceId] = []; // inicia vazio

  }

  public function onMessage(ConnectionInterface $from, $msg) {
    $data = json_decode($msg, true);
    if (isset($data['subscribe']) && is_string($data['subscribe'])) {
        $this->subscriptions[$from->resourceId] = [$data['subscribe']];
    }
  }

  public function onClose(ConnectionInterface $conn) {
    $this->clients->detach($conn);
    unset($this->subscriptions[$conn->resourceId]);
  }

  public function onError(ConnectionInterface $conn, \Exception $e) {
    $conn->close();
  }

  public function sendToSubscribedClients($topic, $msg) {
    foreach ($this->clients as $client) {
      $subs = $this->subscriptions[$client->resourceId] ?? [];
      if (in_array($topic, $subs)) {
          $client->send($msg);
      }
    }
  }
}

$wsHandler = new MqttWebSocket();

$loop = React\EventLoop\Factory::create();

$webSock = new React\Socket\Server("0.0.0.0:$wsPort", $loop);
$webServer = new IoServer(new HttpServer(new WsServer($wsHandler)), $webSock, $loop);

$service = new MqttService($ip, $port);
// Supondo que o MqttService tem métodos compatíveis com ReactPHP ou tem que adaptar o loop

$lastMessages = [];

$service->connect();

$service->subscribeWithCallback($topics, function ($topic, $message) use (&$lastMessages, $wsHandler) {
  $lastMessages[$topic] = $message;
  $data = json_encode(['topic' => $topic, 'message' => $message]);
  $wsHandler->sendToSubscribedClients($topic, $data);
});

// Servidor HTTP para responder /last-message
$httpServer = new HttpServer(function (ServerRequestInterface $request) use (&$lastMessages) {
  $path = $request->getUri()->getPath();
  $queryParams = $request->getQueryParams();

  if ($path === '/last-message' && isset($queryParams['topic'])) {
      $topic = $queryParams['topic'];
      if (isset($lastMessages[$topic])) {
          return new Response(
              200,
              ['Content-Type' => 'application/json'],
              json_encode(['message' => $lastMessages[$topic]])
          );
      }
      return new Response(404, ['Content-Type' => 'application/json'], json_encode(['error' => 'Topic not found']));
  }
  return new Response(404, ['Content-Type' => 'application/json'], 'Not Found');
});
$httpSock = new React\Socket\Server('0.0.0.0:' . $httpPort, $loop);
$httpServer->listen($httpSock);

// Já tens o WebSocket e o HTTP rodando no mesmo $loop
$loop->run();