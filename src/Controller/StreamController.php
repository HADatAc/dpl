<?php

namespace Drupal\dpl\Controller;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\rep\Vocabulary\VSTOI;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\SettingsCommand;
use Drupal\Core\File\FileSystemInterface;
use Drupal\rep\Vocabulary\HASCO;
use Drupal\rep\Utils;
use Drupal\rep\Constant;

class StreamController extends ControllerBase {

  use StringTranslationTrait;

  public function streamTopicSubscribe($topicuri) {
    $streamtopicUri = base64_decode($topicuri);

    try {
      $api = \Drupal::service('rep.api_connector');

      $streamTopic = $api->parseObjectResponse(
        $api->getUri($streamtopicUri),
        'getUri'
      );

      // dpm($streamTopic);

      if (!$streamTopic) {
        return new JsonResponse(['status' => 'error', 'message' => 'Stream Topic not found.'], 404);
      }

      // dpm(rawurlencode($streamTopic->uri));
      $api->streamTopicSubscribe($streamTopic->uri);

      return new JsonResponse(['status' => 'ok', 'message' => 'Stream Topic has been Subscribed']);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'status' => 'error',
        'message' => 'Erro: ' . $e->getMessage(),
      ], 500);
    }
  }

  public function streamTopicUnsubscribe($topicuri) {
    $streamtopicUri = base64_decode($topicuri);

    try {
      $api = \Drupal::service('rep.api_connector');

      $streamTopic = $api->parseObjectResponse(
        $api->getUri($streamtopicUri),
        'getUri'
      );
      if (!$streamTopic) {
        return new JsonResponse(['status' => 'error', 'message' => 'Stream Topic not found.'], 404);
      }

      $api->streamTopicUnsubscribe($streamTopic->uri);

      return new JsonResponse(['status' => 'ok', 'message' => 'Stream Topic has been Unsubscribed']);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'status' => 'error',
        'message' => 'Erro: ' . $e->getMessage(),
      ], 500);
    }
  }

  public function streamTopicStatus($topicuri, $status) {
    $streamtopicUri = base64_decode($topicuri);
    $statusTopic = base64_decode($status);


    try {
      $api = \Drupal::service('rep.api_connector');

      $streamTopic = $api->parseObjectResponse(
        $api->getUri($streamtopicUri),
        'getUri'
      );
      if (!$streamTopic) {
        return new JsonResponse(['status' => 'error', 'message' => 'Stream Topic not found.'], 404);
      }

      $api->streamTopicSetStatus($streamTopic->uri, $statusTopic);

      $message = $this->t(
        'Stream Topic has @status.',
        ['@status' => Utils::plainStatus($statusTopic)]
      );

      // Ou, sem tradução:
      $message = 'Stream Topic has ' . Utils::plainStatus($statusTopic) . '.';

      return new JsonResponse([
        'status'  => 'ok',
        'message' => $message,
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'status' => 'error',
        'message' => 'Erro: ' . $e->getMessage(),
      ], 500);
    }
  }

  public function streamTopicLatesMessage($topicuri) {
    $streamtopicUri = base64_decode($topicuri);

    try {
      $api = \Drupal::service('rep.api_connector');

      $streamTopic = $api->parseObjectResponse(
        $api->getUri($streamtopicUri),
        'getUri'
      );
      if (!$streamTopic) {
        return new JsonResponse(['status' => 'error', 'message' => 'Stream Topic not found.'], 404);
      }

      $reponse = $api->streamTopicLatestMessage($streamTopic->uri);
      $obj = json_decode($reponse);
      $messages = [];
      if ($obj->isSuccessful) {
        $messages = $obj->body;
      }

      return new JsonResponse(['status' => 'ok', 'messages' => $messages]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'status' => 'error',
        'message' => 'Erro: ' . $e->getMessage(),
      ], 500);
    }
  }

  public function streamTopicExpose($topicuri, $brokerip, $brokerport ) {
    $streamtopicUri = base64_decode($topicuri);
    $brokerIp = base64_decode($brokerip);
    $brokerPort = intval(base64_decode($brokerport));

    try {
      $api = \Drupal::service('rep.api_connector');

      $streamTopic = $api->parseObjectResponse(
        $api->getUri($streamtopicUri),
        'getUri'
      );
      if (!$streamTopic) {
        return new JsonResponse(['status' => 'error', 'message' => 'Stream Topic not found.'], 404);
      }

      $api->streamTopicExpose($streamTopic->uri, $brokerIp, $brokerPort);

      $message = $this->t(
        'Stream Topic on @brokerIP:@brokerPort.',
        ['@brokerIP' => Utils::plainStatus($$brokerIp), '@brokerPort' => Utils::plainStatus($brokerPort)]
      );

      $message = 'Stream Topic exposing to ' . $brokerIp . ':' .$brokerPort;

      return new JsonResponse([
        'status'  => 'ok',
        'message' => $message,
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * AJAX endpoint to ingest a file.
   */
  public function fileIngestAjax(Request $request) {
    // 1) Read the base64-encoded element URI from the query string.
    $elementuri = $request->query->get('elementuri', '');
    if (empty($elementuri)) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('No file selected.'),
      ], 400);
    }

    // 2) Decode and validate.
    $decoded = base64_decode($elementuri, TRUE);
    if ($decoded === FALSE) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Invalid file identifier.'),
      ], 400);
    }

    // 3) Use your Rep API to perform the ingest.
    /** @var \Drupal\rep\ApiConnectorInterface $api */
    $api = \Drupal::service('rep.api_connector');

    // Fetch the template metadata for this DA.
    $template = $api->parseObjectResponse($api->getUri($decoded), 'getUri');
    // Trigger the upload/ingest operation.
    $msg = $api->parseObjectResponse(
      $api->uploadTemplate('da', $template, \Drupal\rep\Vocabulary\VSTOI::CURRENT),
      'uploadTemplateStatus'
    );

    // 4) Return error if the API didn’t give us a status back.
    if (empty($msg)) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('The file submission failed.'),
      ], 500);
    }

    // 5) All good — success!
    return new JsonResponse([
      'status' => 'success',
      'message' => $this->t('The file was successfully submitted for ingestion.'),
    ]);
  }

  public function fileUningestAjax(Request $request) {
    // 1) Read the base64‐encoded element URI from the query string.
    $elementuri = $request->query->get('elementuri', '');
    if (empty($elementuri)) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('No file selected for uningestion.'),
      ], 400);
    }

    // 2) Decode and validate.
    $decoded = base64_decode($elementuri, TRUE);
    if ($decoded === FALSE) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Invalid file identifier.'),
      ], 400);
    }

    // 3) Call your Rep API to uningest the MT.
    /** @var \Drupal\rep\ApiConnectorInterface $api */
    $api = \Drupal::service('rep.api_connector');
    // Assuming your API connector has a method named `uningestMT`.
    $msg = $api->parseObjectResponse(
      $api->uningestMT($decoded),
      'uningestMT'
    );

    // 4) On failure, return an error.
    if (empty($msg)) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('The file submission for uningestion failed.'),
      ], 500);
    }

    // 5) On success, return JSON success.
    return new JsonResponse([
      'status' => 'success',
      'message' => $this->t('The file was successfully submitted for uningestion.'),
    ]);
  }

  public static function readMessages($filename) {
    $filepath = 'private://streams/messageFiles/live/' . basename($filename) . '.txt'; // segurança extra com basename
    $real_path = \Drupal::service('file_system')->realpath($filepath);
    if (!file_exists($real_path)) {
      \Drupal::logger('dpl')->debug('O ficheiro de mensagens não existe: @path', ['@path' => $real_path]);
      return ['messages' => []];
    }

    $lines = file($real_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
      \Drupal::logger('dpl')->debug('O ficheiro está vazio ou ocorreu um erro na leitura: @path', ['@path' => $real_path]);
      return ['messages' => []];
    }

    $latest_two = array_slice($lines, -2);

    \Drupal::logger('dpl')->debug('Últimas 2 mensagens: @lines', ['@lines' => print_r($latest_two, true)]);

    return [
      'messages' => $latest_two,
    ];
  }

}
