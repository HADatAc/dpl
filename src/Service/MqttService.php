<?php

namespace Drupal\dpl\Service;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\Exceptions\MqttClientException;
use Drupal\Core\Cache\CacheBackendInterface;

class MqttService {

    protected $client;
    protected $cache;
    protected $topics;
  
    public function __construct($ip, $port, CacheBackendInterface $cache_backend) {
      $clientId = 'dpl_client_' . uniqid();
      $this->client = new MqttClient($ip, $port, $clientId);
      $this->cache = $cache_backend;
      $this->topics = [];
    }
  
    public function connect(): void {
      try {
        $this->client->connect();
      } catch (MqttClientException $e) {
        \Drupal::logger('dpl')->error('MQTT connect error: @msg', ['@msg' => $e->getMessage()]);
      }  
      
    }
  
    public function subscribe(array $topics): void {
      foreach ($topics as $topic) {
        $this->topics[] = $topic;
        $this->client->subscribe($topic, function (string $topic, string $message) {
          $this->handleMessage($topic, $message);
        }, 0);
      }
    }
  
    protected function handleMessage(string $topic, string $message): void {
      $cid = 'mqtt_messages:' . $this->sanitizeCid($topic);
      $existing = $this->cache->get($cid);
      $messages = $existing ? $existing->data : [];
  
      $messages[] = $message;
  
      // Mantém só as últimas 10
      if (count($messages) > 10) {
        array_shift($messages);
      }
  
      $this->cache->set($cid, $messages);
    }
  
    public function loop(): void {
      $this->client->loop(true);
    }
  
    protected function sanitizeCid($topic) {
      return str_replace(['/', '#', '+'], '_', $topic);
    }
  
    public function disconnect(): void {
      $this->client->disconnect();
    }
  }