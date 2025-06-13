<?php

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;
use Drupal\dpl\Service\MqttService;

require_once '/opt/drupal/web/autoload.php';

$kernel = DrupalKernel::createFromRequest(Request::createFromGlobals(), $autoloader, 'prod');
$kernel->boot();

// Lê argumentos CLI
$options = getopt('', ['ip:', 'port:', 'topics:']);
$ip = $options['ip'];
$port = $options['port'];
$topics = explode(',', $options['topics']);

// Cria chave para memória partilhada
$shmKey = ftok(__FILE__, 'm');
$shmSize = 8192;
$shmId = shmop_open($shmKey, 'c', 0644, $shmSize);
if (!$shmId) {
  \Drupal::logger('dpl')->error("Falha ao criar shmop para MQTT.");
  exit(1);
}

$service = new MqttService($ip, $port);

$service->connect();
\Drupal::logger('dpl')->debug('MQTT conectado a @ip:@port', ['@ip' => $ip, '@port' => $port]);

$service->subscribeWithCallback($topics, function ($topic, $message) use ($shmId) {
  // Lê o conteúdo atual da memória
  $raw = shmop_read($shmId, 0, 8192);
  \Drupal::logger('dpl')->debug('Mensagem recebida de @topic: @msg', ['@topic' => $topic, '@msg' => $message]);
  \Drupal::logger('dpl')->debug('Conteúdo atual da memória: @raw', ['@raw' => $raw]);

  $existing = json_decode(trim($raw), true);

  if (!is_array($existing)) {
    \Drupal::logger('dpl')->debug('Memória inválida. Recriando estrutura...');
    $existing = [];
  }

  $existing[$topic] = $message;

  // Codifica novamente
  $payload = json_encode($existing);

  if (strlen($payload) > 8192) {
    \Drupal::logger('dpl')->warning('Memória cheia. Ignorada mensagem de @topic', ['@topic' => $topic]);
    return;
  }

  // Escreve na memória
  $payload = str_pad($payload, 8192); // Garantir tamanho fixo
  shmop_write($shmId, $payload, 0);

  \Drupal::logger('dpl')->debug('Mensagem de @topic guardada na memória.', ['@topic' => $topic]);
});

\Drupal::logger('dpl')->notice("Subscrito aos tópicos: " . implode(', ', $topics));

// Loop infinito
while (true) {
  $service->loop();
  usleep(100000); // evita CPU 100%
}

// Fecha shmop no fim
shmop_close($shmId);