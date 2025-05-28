<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dpl\Form\ListStreamStateByDeploymentPage;

class MqttMessagesRecordForm extends FormBase {

  public function getFormId() {
    return 'dpl_mqtt_messages_record_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $streamuri = NULL, $state = NULL, $email = NULL, $deploymenturi = NULL, $page = NULL, $pagesize = NULL) {
    $stream_uri = base64_decode($streamuri);
    $deploymenturi = base64_decode($deploymenturi);
    $state = base64_decode($state);

    $streams = ListStreamStateByDeploymentPage::exec(rawurlencode($state), $email, $deploymenturi, $page, $pagesize);

    if (empty($streams) || !is_iterable($streams)) {
      \Drupal::messenger()->addError($this->t("No streams found (or error fetching from API)."));
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
    $archive_id = $stream->messageArchiveId ?? 'UnknownArchive';

    // Apenas exibição
    $form['info'] = [
      '#markup' => "<p><strong>IP:</strong> $ip<br><strong>Port:</strong> $port<br><strong>Topic:</strong> $topic</p>",
    ];

    // Anexar JS e settings
    $form['#attached']['library'][] = 'dpl/mqtt_polling';
    $form['#attached']['drupalSettings']['mqtt_record'] = [
      'archive_id' => $archive_id,
      'ip' => $ip,
      'port' => $port,
      'topic' => $topic,
    ];

    return $form;
  }
}
