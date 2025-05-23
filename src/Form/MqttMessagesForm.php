<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dpl\Form\ListStreamStateByDeploymentPage;
use Bluerhinos\phpMQTT;


class MqttMessagesForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dpl_mqtt_messages_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $streamuri = NULL, $state = NULL, $email = NULL, $deploymenturi = NULL, $page = NULL, $pagesize = NULL) {
    $stream_uri = base64_decode($streamuri);
    $deployment_uri = base64_decode($deploymenturi);
  
    $streams = ListStreamStateByDeploymentPage::exec($state, $email, $deployment_uri, $page, $pagesize);
  
    $stream = NULL;
    foreach ($streams as $s) {
      if ($s->uri == $stream_uri) {
        $stream = $s;
        break;
      }
    }
  
    if (!$stream) {
      \Drupal::messenger()->addError($this->t("Stream not found."));
      return $form;
    }
  
    $ip = $stream->messageIP ?? NULL;
    $port = $stream->messagePort ?? NULL;
    $topic = 'wsaheadhin';

    $form['info'] = [
      '#markup' => "<p><strong>IP:</strong> $ip<br><strong>Port:</strong> $port<br><strong>Topic:</strong> $topic</p>",
    ];

    // 2. Obter mensagens MQTT
    $messages = $this->readMqttMessages($ip, $port, $topic);
    $form_state->set('mqtt_messages', $messages);

    if (empty($messages)) {
      $output = '<em>No messages received.</em>';
    } else {
      $output = '<ul>';
      foreach ($messages as $msg) {
        $output .= '<li>' . htmlspecialchars($msg) . '</li>';
      }
      $output .= '</ul>';
    }

    $form['messages'] = [
      '#type' => 'markup',
      '#markup' => "<h3>MQTT Messages</h3>$output",
    ];

    // 3. Botão para guardar (opcional)
    $form['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Raw Messages'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Guardar mensagens no ficheiro
    // $messages = $form_state->get('mqtt_messages');
    // if (!empty($messages)) {
    //   $filename = 'private://mqtt_messages_' . time() . '.txt';
    //   file_put_contents($filename, implode(PHP_EOL, $messages));
    //   \Drupal::messenger()->addStatus($this->t('Messages saved to @file', ['@file' => $filename]));
    // } else {
      \Drupal::messenger()->addWarning($this->t('No messages to save.'));
    // }
  }

  private function readMqttMessages($ip, $port, $topic) {
    $client_id = 'drupal_' . uniqid();
    $mqtt = new phpMQTT($ip, (int) $port, $client_id);
    $messages = [];

    if ($mqtt->connect(true, NULL, '', '')) {
      $mqtt->subscribe([$topic => ['qos' => 0, 'function' => function($topic, $msg) use (&$messages) {
        $messages[] = $msg;
      }]]);

      // Processa mensagens por 2 segundos
      $start = time();
      while (time() - $start < 2) {
        $mqtt->proc();
      }

      $mqtt->close();
    }

    // Guardar no state para uso posterior
    \Drupal::service('tempstore.private')->get('mqtt')->set('last_messages', $messages);

    return $messages;
  }
}
