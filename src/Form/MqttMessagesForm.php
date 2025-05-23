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
    $deploymenturi = base64_decode($deploymenturi);
    $state = base64_decode($state);
 
    $streams = ListStreamStateByDeploymentPage::exec(rawurlencode($state), $email, $deploymenturi, $page, $pagesize);

    if (empty($streams) || !is_iterable($streams)) {
        \Drupal::messenger()->addError($this->t("No streams found (or error fetching from API)."));
        \Drupal::logger('mqtt')->error('Streams list is null or not iterable. Input: state=@state, email=@email, deployment_uri=@uri, page=@page, pagesize=@pagesize', [
          '@state' => $state,
          '@email' => $email,
          '@uri' => $deploymenturi,
          '@page' => $page,
          '@pagesize' => $pagesize,
        ]);
        return $form;
    }
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

    $results = $this->readMqttMessages($ip, $port, $topic);
    $debug_info = $results['debug'];
    $messages = $results['messages'];
    $form_state->set('mqtt_messages', $messages);
    
    $output = '<div class="mqtt-messages">';
    $output .= $debug_info;
    
    if (empty($messages)) {
      $output .= '<em>No messages received.</em>';
    } else {
      foreach ($messages as $msg) {
        $decoded = json_decode($msg, true);
        if (json_last_error() === JSON_ERROR_NONE) {
          $output .= '<div class="mqtt-card" style="border:1px solid #ccc; margin-bottom:10px; padding:10px; border-radius:5px;">';
          $output .= '<pre style="margin:0;"><strong>Tópico:</strong> ' . htmlspecialchars($topic) . '</pre>';
          foreach ($decoded as $key => $value) {
            $output .= '<div><strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars((string) $value) . '</div>';
          }
          $output .= '</div>';
        } else {
          $output .= '<div class="mqtt-raw">' . htmlspecialchars($msg) . '</div>';
        }
      }
    }
    $output .= '</div>';

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
    $private_key = '/var/www/.ssh/graxiom_main.pem';
    $ssh_user = 'ubuntu';
    $remote_cmd = 'tmux capture-pane -pt mqtt -S -100 -e';
    $ssh_cmd = "ssh -i $private_key -o StrictHostKeyChecking=no $ssh_user@$ip '$remote_cmd' 2>&1";
  
    $output = shell_exec($ssh_cmd);
  
    $debug_info = "<pre><strong>Comando executado:</strong> $ssh_cmd\n\n";
    $debug_info .= "<strong>Output bruto:</strong>\n" . htmlspecialchars($output) . "</pre>";
  
    if (empty(trim($output))) {
        return ['debug' => $debug_info, 'messages' => []];
      }
    
      preg_match_all('/\{.*?\}/s', $output, $matches);
    
      return [
        'debug' => $debug_info,
        'messages' => $matches[0] ?? []
      ];
  }
}
