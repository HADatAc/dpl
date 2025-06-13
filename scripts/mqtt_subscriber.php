<?php

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;
use Drupal\dpl\Service\MqttService;

use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

require_once '/opt/drupal/web/autoload.php';
require_once '/opt/drupal/vendor/autoload.php';

$kernel = DrupalKernel::createFromRequest(Request::createFromGlobals(), $autoloader, 'prod');
$kernel->boot();

$options = getopt('', ['ip:', 'port:', 'topics:', 'ws-port:']);
$ip = $options['ip'];
$port = $options['port'];
$topics = explode(',', $options['topics']);
$wsPort = $options['ws-port'] ?? 8081;

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

$service->subscribeWithCallback($topics, function ($topic, $message) use ($wsHandler) {
  $data = json_encode(['topic' => $topic, 'message' => $message]);
  $wsHandler->sendToSubscribedClients($topic, $data);
});

// Adaptar para integrar o MQTT loop com ReactPHP loop
// Se o teu MqttService não tem suporte ReactPHP, tens que correr $service->loop() periodicamente
$loop->addPeriodicTimer(0.1, function() use ($service) {
  $service->loop();
});

$loop->run();