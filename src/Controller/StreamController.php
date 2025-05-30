<?php

namespace Drupal\dpl\Controller;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\rep\Vocabulary\VSTOI;
use Symfony\Component\HttpFoundation\Request;
use Drupal\rep\Vocabulary\HASCO;

class StreamController extends ControllerBase {

  use StringTranslationTrait;

  
  public function streamRecord($streamUri) {
    // Your logic to handle the stream record display.
    // $payload = [
    //   'uri'                       => $this->stream->uri,
    //   'typeUri'                   => HASCO::STREAM,
    //   'hascoTypeUri'              => HASCO::STREAM,
    //   'label'                     => 'Stream',
    //   'method'                    => $form_state->getValue('stream_method'),
    //   'deploymentUri'             => $this->stream->deploymentUri,
    //   'studyUri'                  => Utils::uriFromAutocomplete($form_state->getValue('stream_study')),
    //   'semanticDataDictionaryUri' => Utils::uriFromAutocomplete($form_state->getValue('stream_semanticdatadictionary')),
    //   'hasVersion'                => $form_state->getValue('stream_version') ?? $this->stream->hasVersion,
    //   'comment'                   => $form_state->getValue('stream_description'),
    //   'canUpdate'                 => [$email],
    //   'designedAt'                => $this->stream->designedAt,
    //   'hasSIRManagerEmail'        => $email,
    //   'hasStreamStatus'           => $this->stream->hasStreamStatus,
    // ];

    // if ($form_state->getValue('stream_method') === 'files') {
    //   $payload['datasetPattern'] = $form_state->getValue('stream_datafile_pattern');
    //   $payload['cellScopeUri']    = [$form_state->getValue('stream_cell_scope_uri')];
    //   $payload['cellScopeName']   = [$form_state->getValue('stream_cell_scope_name')];
    //   $payload['messageProtocol']  = '';
    //   $payload['messageIP']        = '';
    //   $payload['messagePort']      = '';
    //   $payload['messageArchiveId'] = '';
    //   // $payload['messageHeader']    = '';
    // }
    // else {
    //   $payload['messageProtocol']   = $form_state->getValue('stream_protocol');
    //   $payload['messageIP']         = $form_state->getValue('stream_ip');
    //   $payload['messagePort']       = $form_state->getValue('stream_port');
    //   $payload['messageArchiveId']  = $form_state->getValue('stream_archive_id');
    //   // $payload['messageHeader']     = $form_state->getValue('stream_header');
    //   $payload['datasetPattern']   = '';
    //   $payload['cellScopeUri']      = [];
    //   $payload['cellScopeName']     = [];
    //   $payload['hasMessageStatus']  = HASCO::RECORDING;
    // }

    // try {
    //   $api = \Drupal::service('rep.api_connector');
    //   // Delete and re-create to perform update.
    //   $api->elementDel('stream', $this->stream->uri);
    //   $api->elementAdd('stream', json_encode($payload));
    //   \Drupal::messenger()->addMessage($this->t('Stream has been updated successfully.'));
    // }
    // catch (\Exception $e) {
    //   \Drupal::messenger()->addError($this->t('An error occurred while updating the Stream: @msg', ['@msg' => $e->getMessage()]));
    // }
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
  }

  public function streamIngest($streamUri) {
    // Your logic to handle ingesting the stream.
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
    $remote_cmd = 'tmux capture-pane -pt mqtt -S -1 -e';
    $ssh_cmd = "ssh -i $private_key -o StrictHostKeyChecking=no $ssh_user@$ip '$remote_cmd' 2>&1";

    $output = shell_exec($ssh_cmd);

    $debug_info = "<pre><strong>Comando executado:</strong> $ssh_cmd\n\n";

    if (empty(trim($output))) {
      return ['debug' => $debug_info, 'messages' => []];
    }

    preg_match_all('/\{.*?\}/s', $output, $matches);
    $all_messages = $matches[0] ?? [];
    $latest_one = array_slice($all_messages, -1);

    return [
      'debug' => $debug_info,
      'messages' => $latest_one,
    ];
  }

}
