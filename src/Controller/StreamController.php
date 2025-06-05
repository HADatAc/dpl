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

class StreamController extends ControllerBase {

  use StringTranslationTrait;


  public function streamRecord($streamUri) {
    $streamUri = base64_decode($streamUri);
  
    try {
      $api = \Drupal::service('rep.api_connector');
  
      $stream = $api->parseObjectResponse(
        $api->getUri($streamUri),
        'getUri'
      );
      if (!$stream) {
        return new JsonResponse(['status' => 'error', 'message' => 'Stream not found.'], 404);
      }
  
      // Atualizar o estado da stream
      $stream->hasMessageStatus = HASCO::RECORDING;
      $recordStartTime = date('Y-m-d H:i:s');
  
      // Reconstruir o payload com os dados existentes + atualização
      $payload = [
        'uri' => $stream->uri,
        'typeUri' => HASCO::STREAM,
        'hascoTypeUri' => HASCO::STREAM,
        'label' => $stream->label ?? 'Stream',
        'deploymentUri' => $stream->deploymentUri ?? '',
        'studyUri' => $stream->studyUri ?? '',
        'semanticDataDictionaryUri' => $stream->semanticDataDictionaryUri ?? '',
        'method' => $stream->method ?? '',
        'startedAt' => $stream->startedAt ?? '',
        'datasetPattern' => $stream->datasetPattern ?? '',
        'cellScopeUri' => $stream->cellScopeUri ?? [],
        'cellScopeName' => $stream->cellScopeName ?? [],
        'messageProtocol' => $stream->messageProtocol ?? '',
        'messageIP' => $stream->messageIP ?? '',
        'messagePort' => $stream->messagePort ?? '',
        'messageArchiveId' => $stream->messageArchiveId ?? '',
        'hasVersion' => $stream->hasVersion ?? '',
        'comment' => $stream->comment ?? '',
        'canUpdate' => $stream->canUpdate ?? [],
        'designedAt' => $stream->designedAt ?? '',
        'hasSIRManagerEmail' => $stream->hasSIRManagerEmail ?? '',
        'hasStreamStatus' => $stream->hasStreamStatus ?? '',
        'hasMessageStatus' => HASCO::RECORDING,
      ];
  
      // Atualizar a stream (delete + add)
      $api->elementDel('stream', $stream->uri);
      $api->elementAdd('stream', json_encode($payload));

      return new JsonResponse(['status' => 'ok', 'message' => 'Recording started.']);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'status' => 'error',
        'message' => 'Erro: ' . $e->getMessage(),
      ], 500);
    }  
  }

  public function streamSuspend($streamUri) {

    $streamUri = base64_decode($streamUri);

    try {
      $api = \Drupal::service('rep.api_connector');
      $stream = $api->parseObjectResponse($api->getUri($streamUri), 'getUri');
      \Drupal::logger('debug')->debug('<pre>@stream suspend</pre>', ['@stream' => print_r($stream, TRUE)]);

      if (!$stream || empty($stream->messageArchiveId) || empty($stream->startedAt)) {
        return new JsonResponse(['status' => 'error', 'message' => 'Missing data in stream.'], 400);
      }
  
      $archiveId = $stream->messageArchiveId;

      try {
        $recordStart = new \DateTime($stream->startedAt);
      } catch (\Exception $e) {
        \Drupal::logger('debug')->error('Erro ao ir buscar a data: @error', ['@error' => $e->getMessage()]);
        return new JsonResponse(['status' => 'error', 'message' => 'Invalid start time format.'], 400);
      }
  
      $file_path = 'private://streams/messageFiles/' . $archiveId . '.txt';
      $real_path = \Drupal::service('file_system')->realpath($file_path);
  
      if (!file_exists($real_path)) {
        \Drupal::logger('debug')->error('Erro ao ir buscar o ficheiro original');
        return new JsonResponse(['status' => 'error', 'message' => 'Original file not found.'], 404);
      }
  
      $lines = file($real_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      $filtered = [];
  
      foreach ($lines as $line) {
        // Separar tópico e mensagem
        [$topic, $json] = explode(' ', $line, 2);
        $data = json_decode($json, true);
  
        if (json_last_error() !== JSON_ERROR_NONE || empty($data['timestamp'])) {
          continue;
        }
  
        $msgTime = \DateTime::createFromFormat('Y-m-d H:i:s', $data['timestamp']);
        if ($msgTime && $msgTime >= $recordStart) {
          $filtered[] = $line;
        }
      }
      dpm(empty($filtered));return false;
      if (empty($filtered)) {
        return new JsonResponse(['status' => 'ok', 'message' => 'No messages to record.']);
      }
  
      $target_dir = 'private://streams/messageFilesRecord/';
      \Drupal::service('file_system')->prepareDirectory($target_dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
  
      // Calcular o número sequencial
      $existing = file_scan_directory($target_dir, '/^' . preg_quote($archiveId) . '_\d+\.txt$/');
      $seq = count($existing);
  
      // Escrever novo ficheiro
      $new_filename = $archiveId . '_' . $seq . '.txt';
      $new_filepath = $target_dir . $new_filename;
      $data = implode(PHP_EOL, $filtered);

      try {
        \Drupal::logger('debug')->info('A gravar ficheiro: @file', ['@file' => $new_filepath]);
        \Drupal::service('file_system')->saveData($data, $new_filepath, FileSystemInterface::EXISTS_REPLACE);
      } catch (\Exception $e) {
        \Drupal::logger('debug')->error('Erro ao gravar os dados: @error', ['@error' => $e->getMessage()]);
        return new JsonResponse(['status' => 'error', 'message' => 'Erro ao criar o ficheiro de gravação.'], 500);
      }
      // Atualizar estado da stream
      $stream->hasMessageStatus = HASCO::SUSPENDED;
      $payload = [
        'uri' => $stream->uri,
        'typeUri' => HASCO::STREAM,
        'hascoTypeUri' => HASCO::STREAM,
        'label' => $stream->label ?? 'Stream',
        'deploymentUri' => $stream->deploymentUri ?? '',
        'studyUri' => $stream->studyUri ?? '',
        'semanticDataDictionaryUri' => $stream->semanticDataDictionaryUri ?? '',
        'method' => $stream->method ?? '',
        'startedAt' => $stream->startedAt ?? '',
        'datasetPattern' => $stream->datasetPattern ?? '',
        'cellScopeUri' => $stream->cellScopeUri ?? [],
        'cellScopeName' => $stream->cellScopeName ?? [],
        'messageProtocol' => $stream->messageProtocol ?? '',
        'messageIP' => $stream->messageIP ?? '',
        'messagePort' => $stream->messagePort ?? '',
        'messageArchiveId' => $stream->messageArchiveId ?? '',
        'hasVersion' => $stream->hasVersion ?? '',
        'comment' => $stream->comment ?? '',
        'canUpdate' => $stream->canUpdate ?? [],
        'designedAt' => $stream->designedAt ?? '',
        'hasSIRManagerEmail' => $stream->hasSIRManagerEmail ?? '',
        'hasStreamStatus' => $stream->hasStreamStatus ?? '',
        'hasMessageStatus' => HASCO::SUSPENDED,
      ];
  
      $api->elementDel('stream', $stream->uri);
      $api->elementAdd('stream', json_encode($payload));

      $filename = $new_filename;
      $fileId = $archiveId . '_' . $seq;
      $studyUri = $stream->studyUri ?? '';
      $useremail = \Drupal::currentUser()->getEmail();
      $newDataFileUri = Utils::uriGen('datafile');

      $datafileArr = [
        'uri'               => $newDataFileUri,
        'typeUri'           => HASCO::DATAFILE,
        'hascoTypeUri'      => HASCO::DATAFILE,
        'label'             => $filename,
        'filename'          => $filename,
        'id'                => $fileId,
        'studyUri'          => $studyUri,
        'streamUri'         => $stream->uri,
        'fileStatus'        => Constant::FILE_STATUS_UNPROCESSED,
        'hasSIRManagerEmail'=> $useremail,
      ];
      $datafileJSON = json_encode($datafileArr);
      
      // Criar DA JSON
      $newMTUri = str_replace("DFL", Utils::elementPrefix('da'), $newDataFileUri);
      $mtArr = [
        'uri'             => $newMTUri,
        'typeUri'         => HASCO::DATA_ACQUISITION,
        'hascoTypeUri'    => HASCO::DATA_ACQUISITION,
        'isMemberOfUri'   => $studyUri,
        'label'           => $filename,
        'hasDataFileUri'  => $newDataFileUri,
        'hasVersion'      => '',
        'comment'         => '',
        'hasSIRManagerEmail'=> $useremail,
      ];
      $mtJSON = json_encode($mtArr);
      
      // Enviar para a API
      $api->parseObjectResponse($api->datafileAdd($datafileJSON), 'datafileAdd');
      $api->parseObjectResponse($api->elementAdd('da', $mtJSON), 'elementAdd');


  
      return new JsonResponse(['status' => 'ok', 'message' => 'Gravação suspensa e ficheiro criado com sucesso.']);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'status' => 'error',
        'message' => 'Erro: ' . $e->getMessage(),
      ], 500);
    }
  }

  public function streamIngest($streamUri) {
    return new Response('Página placeholder para streamIngest.');
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
    $filepath = 'private://streams/messageFiles/' . basename($filename) . '.txt'; // segurança extra com basename
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
