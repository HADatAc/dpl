<?php

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;
use Drupal\dpl\Service\MqttService;

// Bootstrap Drupal para usar o serviço
$autoloader = require_once 'web/autoload.php';
$kernel = DrupalKernel::createFromRequest(Request::createFromGlobals(), $autoloader, 'prod');
$kernel->boot();

// Lê parâmetros via argumentos CLI
$options = getopt('', ['ip:', 'port:', 'topics:']);

$ip = $options['ip'];
$port = $options['port'];
$topics = explode(',', $options['topics']);

$cache = \Drupal::service('cache.default');
$service = new MqttService($ip, $port, $cache);

$service->connect();
$service->subscribe($topics);

// Loop infinito
while (true) {
  $service->loop();
  usleep(100000); // evita CPU 100%
}
