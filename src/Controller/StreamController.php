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

      // Iniciar script worker para gravar a stream
      $php_path = '/usr/local/bin/php'; // Ajustar se o PHP estiver noutro caminho
      $script_path = '/opt/drupal/web/modules/custom/dpl/scripts/stream_worker.php';

      $cmd = escapeshellcmd("$php_path $script_path " .
        escapeshellarg($stream->uri) . ' ' .
        escapeshellarg($stream->messageArchiveId) . ' ' .
        escapeshellarg($stream->messageIP) . ' ' .
        escapeshellarg($stream->messagePort) . ' ' .
        escapeshellarg('wsaheadin') . ' > /dev/null 2>&1 & echo $!'
      );
      \Drupal::logger('stream_record')->debug('Comando: @cmd', ['@cmd' => $cmd]);


      $pid = shell_exec($cmd);
      if ($pid) {
        \Drupal::logger('stream_record')->notice('Script iniciado com PID: @pid', ['@pid' => $pid]);
      } else {
        \Drupal::logger('stream_record')->error('Falha ao iniciar o script worker.');
      }
      $fs = \Drupal::service('file_system');
      $directory = 'private://streams';
      $fs->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      $pid_file = "private://streams/pid_" . md5($stream->uri) . ".txt";
      $fs->saveData($pid, $pid_file, FileSystemInterface::EXISTS_REPLACE);
      return new JsonResponse(['status' => 'ok', 'message' => 'Recording started.']);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'status' => 'error',
        'message' => 'Erro: ' . $e->getMessage(),
      ], 500);
    }  
  }

  public function recordMessageAjax(Request $request) {
    
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $archive_id = $request->query->get('archive_id');
    $ip = $request->query->get('ip');
    $port = $request->query->get('port');
    $topic = $request->query->get('topic');

    \Drupal::logger('dpl')->info('recordMessageAjax chamado com archive_id: @id, ip: @ip, port: @port, topic: @topic', [
      '@id' => $archive_id,
      '@ip' => $ip,
      '@port' => $port,
      '@topic' => $topic,
    ]);

    // Ler nova mensagem (adaptar o comando SSH como no teu Form)
    $ssh_cmd = "ssh -i /var/www/.ssh/graxiom_main.pem -o StrictHostKeyChecking=no ubuntu@$ip 'tmux capture-pane -pt " . escapeshellarg($topic) . " -S -1 -e'";
    $output = shell_exec($ssh_cmd);

    if (empty(trim($output))) {
      return new JsonResponse(['status' => 'no-message']);
    }

    preg_match_all('/\{.*?\}/s', $output, $matches);
    $messages = $matches[0] ?? [];
    $last_msg = end($messages);

    if (isset($_SESSION['last_mqtt_message']) && $_SESSION['last_mqtt_message'] === $last_msg) {
      return new JsonResponse(['status' => 'duplicate']);
    }
    
    $_SESSION['last_mqtt_message'] = $last_msg;

    // Gravar no Excel (append)
    $directory = 'private://streams/messageFiles/';
    \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $filepath = \Drupal::service('file_system')->realpath($directory . "Messages{$archive_id}_0.xlsx");

    if (file_exists($filepath)) {
      $spreadsheet = IOFactory::load($filepath);
    } else {
      $spreadsheet = new Spreadsheet();
      $spreadsheet->getActiveSheet()->fromArray(['Timestamp', 'Raw JSON'], NULL, 'A1');
    }

    $sheet = $spreadsheet->getActiveSheet();
    $row = $sheet->getHighestRow() + 1;
    $sheet->setCellValue("A$row", date('Y-m-d H:i:s'));
    $sheet->setCellValue("B$row", $last_msg);

    $writer = new Xlsx($spreadsheet);
    $writer->save($filepath);

    return new JsonResponse(['status' => 'ok', 'row' => $row]);
  }



  public function streamSuspend($streamUri) {
    // Your logic to handle suspending the stream.
    // Status do messageStream passa a Inactive
    // Enviar para a API o ficheiro DA


    // CRIAR E ENVIAR FICHEIRO DA NA API
    //  Procura função processDAFile em JsonDataController.php (std)
    // 3) Build JSON with json_encode (avoids precedence bugs in ??)
    // $newDataFileUri = Utils::uriGen('datafile');
    // $datafileArr = [
    //     'uri'               => $newDataFileUri,
    //     'typeUri'           => HASCO::DATAFILE,
    //     'hascoTypeUri'      => HASCO::DATAFILE,
    //     'label'             => $filename,
    //     'filename'          => $filename,
    //     'id'                => $fileId,
    //     'studyUri'          => base64_decode($studyuri),
    //     'streamUri'         => $streamUri,
    //     'fileStatus'        => Constant::FILE_STATUS_UNPROCESSED,
    //     'hasSIRManagerEmail'=> $useremail,
    // ];
    // $datafileJSON = json_encode($datafileArr);
    // // \Drupal::logger('debug')->debug('DATAFILE JSON: @json', ['@json' => $datafileJSON]);

    // // Mount the MT JSON
    // $newMTUri = str_replace("DFL", Utils::elementPrefix('da'), $newDataFileUri);
    // $mtArr = [
    //     'uri'             => $newMTUri,
    //     'typeUri'         => HASCO::DATA_ACQUISITION,
    //     'hascoTypeUri'    => HASCO::DATA_ACQUISITION,
    //     'isMemberOfUri'   => base64_decode($studyuri),
    //     'label'           => $filename,
    //     'hasDataFileUri'  => $newDataFileUri,
    //     'hasVersion'      => '',
    //     'comment'         => '',
    //     'hasSIRManagerEmail'=> $useremail,
    // ];
    // $mtJSON = json_encode($mtArr);
    // // \Drupal::logger('debug')->debug('MT JSON: @json', ['@json' => $mtJSON]);

    // // 4) Call API and log responses
    // $msg1 = $api->parseObjectResponse($api->datafileAdd($datafileJSON), 'datafileAdd');
    // // \Drupal::logger('debug')->debug('Response datafileAdd: @resp', [
    // //     '@resp' => print_r($msg1, TRUE),
    // // ]);

    // $msg2 = $api->parseObjectResponse($api->elementAdd('da', $mtJSON), 'elementAdd');
    return new Response('Página placeholder para streamSuspend.');
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

  public static function readMessages($ip, $port, $topic) {
    $private_key = '/var/www/.ssh/graxiom_main.pem';
    $ssh_user = 'ubuntu';
    $escaped_topic = escapeshellarg($topic);
    $remote_cmd = "tmux capture-pane -pt $escaped_topic -S -2 -e";
  
    $ssh_cmd = "ssh -i $private_key -o StrictHostKeyChecking=no $ssh_user@$ip '$remote_cmd' 2>&1";

    $output = shell_exec($ssh_cmd);

    $debug_info = "<pre><strong>Comando executado:</strong> $ssh_cmd\n\n";
    \Drupal::logger('stream_debug')->debug($ssh_cmd);


    if (empty(trim($output))) {
      return ['debug' => $debug_info, 'messages' => []];
    }

    preg_match_all('/\{.*?\}/s', $output, $matches);
    $all_messages = $matches[0] ?? [];
    $latest_two = array_slice($all_messages, -2);

    return [
      'debug' => $debug_info,
      'messages' => $latest_two,
    ];
  }

}
