<?php

namespace Drupal\dpl\Controller;
use Drupal\Core\Controller\ControllerBase;
use Drupal\rep\Vocabulary\VSTOI;



class StreamController extends ControllerBase {

  public function streamRecord($streamUri) {
    // Your logic to handle the stream record display.
  }

  public function streamPlay($streamUri) {
    // Your logic to handle the stream playback.
  }

  public function streamPause($streamUri) {
    // Your logic to handle the stream pause.
  }

  public function streamStop($streamUri) {
    // Your logic to handle stopping the stream.
  }

  public function daFileIngestion($daUri) {
    // Your logic to handle the DA file ingestion.
    $api = \Drupal::service('rep.api_connector');
    $template = $api->parseObjectResponse($api->getUri($daUri), 'getUri');
    $msg = $api->parseObjectResponse($api->uploadTemplate('da', $template, VSTOI::CURRENT), 'uploadTemplateStatus');
    if ($msg == NULL) {
      \Drupal::messenger()->addError(t("The DA file selected FAILED to be submited for Ingestion."));
      return;
    }
    \Drupal::messenger()->addMessage(t("The DA file selected was successfully submited for Ingestion."));
    return;
  }

  public function daFileUningest($daUri) {
    // Your logic to handle the status of DA file Uningestion.
    $api = \Drupal::service('rep.api_connector');
    $msg = $api->parseObjectResponse($api->uningestMT($daUri), 'uningestMT');
    if ($msg == NULL) {
      \Drupal::messenger()->addError(t("The DA file selected FAILED to be submited for Uningestion."));
      return;
    }
    \Drupal::messenger()->addMessage(t("The DA file selected was successfully submited for Uningestion."));
    return;
  }

}
