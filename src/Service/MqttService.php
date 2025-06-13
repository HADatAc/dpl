<?php

namespace Drupal\dpl\Service;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\Exceptions\MqttClientException;
use Drupal\Core\Cache\CacheBackendInterface;

class MqttService {

    protected $client;
    protected $topics;
    protected $lastMessages = [];
  
    public function __construct($ip, $port) {
      $clientId = 'dpl_client_' . uniqid();
      $this->client = new MqttClient($ip, $port, $clientId);
      $this->topics = [];
    }
  
    public function connect(): void {
      try {
        $this->client->connect();
      } catch (MqttClientException $e) {
        \Drupal::logger('dpl')->error('MQTT connect error: @msg', ['@msg' => $e->getMessage()]);
      }  
      
    }

    public function subscribeWithCallback(array $topics, callable $callback): void {
      foreach ($topics as $topic) {
        $this->topics[] = $topic;
        $this->client->subscribe($topic, function (string $topic, string $message) use ($callback) {
          $callback($topic, $message);
        }, 0);
      }
    }    
  
  
    public function loop(): void {
      $this->client->loop(true);
    }

    public function disconnect(): void {
      $this->client->disconnect();
    }
  }