<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\rep\Vocabulary\HASCO;

class ExecuteCloseStreamForm extends FormBase {

  protected $mode;

  protected $streamUri;

  protected $stream;

  protected $deployment;

  protected $deploymentUri;

  public function getMode() {
    return $this->mode;
  }
  public function setMode($mode) {
    return $this->mode = $mode;
  }

  public function getStream() {
    return $this->stream;
  }
  public function setStream($stream) {
    return $this->stream = $stream;
  }

  public function getStreamUri() {
    return $this->streamUri;
  }
  public function setStreamUri($uri) {
    return $this->streamUri = $uri;
  }

  public function getDeployment() {
    return $this->deployment;
  }
  public function setDeployment($deployment) {
    return $this->deployment = $deployment;
  }

  public function getDeploymentUri() {
    return $this->deploymentUri;
  }
  public function setDeploymentUri($deploymentUri) {
    return $this->deploymentUri = $deploymentUri;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'execute_close_stream_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $mode = NULL, $streamuri = NULL) {

    // Globals
    $api = \Drupal::service('rep.api_connector');

    if (($mode == NULL) ||
        ($mode != 'execute' && $mode != 'close')) {
      \Drupal::messenger()->addError(t("Invalid Deployment execute/close operation."));
      self::backUrl();
      return;
    }
    $this->setMode($mode);

    // GET STREAM FROM API
    $uri=$streamuri;
    $uri_decode=base64_decode($uri);
    $this->setStreamUri($uri_decode);

    $rawresponse = $api->getUri($this->getStreamUri());
    $obj = json_decode($rawresponse);

    if ($obj->isSuccessful) {
      $this->setStream($obj->body);
      $this->setDeployment($this->getStream()->deployment);
      $this->setDeploymentUri($this->getStream()->deployment->uri);
    } else {
      \Drupal::messenger()->addError(t("Failed to retrieve Stream."));
      self::backUrl();
      return;
    }

    // DEBBUG
    // kint([
    //   'Stream' => $this->getStream(),
    //   'Deployment' => $this->getDeployment(),
    // ]);

    $studyLabel = ' ';
    if (isset($this->getStream()->study) &&
        isset($this->getStream()->study->uri) &&
        isset($this->getStream()->study->label)) {
      $studyLabel = Utils::fieldToAutocomplete(
        $this->getStream()->study->uri,
        $this->getStream()->study->label
      );
    }
    $sddLabel = ' ';
    if (isset($this->getStream()->semanticDataDictionary) &&
        isset($this->getStream()->semanticDataDictionary->uri) &&
        isset($this->getStream()->semanticDataDictionary->label)) {
      $sddLabel = Utils::fieldToAutocomplete(
        $this->getStream()->semanticDataDictionary->uri,
        $this->getStream()->semanticDataDictionary->label
      );
    }

    $validationError = NULL;
    if (!isset($this->getStream()->study) && !isset($this->getStream()->semanticDataDictionary)) {
      $validationError = "Stream is missing both STUDY and SEMANTIC DATA DICTIONARY.";
    }
    if (!isset($this->getStream()->study) && isset($this->getStream()->semanticDataDictionary)) {
      $validationError = "Stream is missing associated STUDY.";
    }
    if (isset($this->getStream()->study) && !isset($this->getStream()->semanticDataDictionary)) {
      $validationError = "Stream is missing associated SEMANTIC DATA DICTIONARY.";
    }

    //dpm($this->getDeployment());

    if ($this->getMode() == 'execute') {
      $form['page_title'] = [
        '#type' => 'item',
        '#title' => $this->t('<h3>Execute Stream</h3>'),
      ];
    }
    if ($this->getMode() == 'close') {
      $form['page_title'] = [
        '#type' => 'item',
        '#title' => $this->t('<h3>Close Streamt</h3>'),
      ];
    }
    $form['stream_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getStream()->label,
      '#disabled' => TRUE,
    ];
    $form['stream_platform_instance'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Study'),
      '#default_value' => $studyLabel,
      '#disabled' => TRUE,
    ];
    $form['stream_instrument_instance'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Semantic Data Dictionary (SDD)'),
      '#default_value' => $sddLabel,
      '#disabled' => TRUE,
    ];

    // DEPLOYMENT IS VALID
    if ($validationError == NULL) {
      if ($this->getMode() == 'execute') {
        $form['stream_start_datetime'] = [
          '#type' => 'datetime',
          '#title' => $this->t('Starting Date/Time'),
          '#default_value' => $this->getStream()->startedAt ? $this->getStream()->startedAt : '',
          '#date_date_element' => 'date', // Use a date element
          '#date_time_element' => 'time', // Use a time element
          '#date_format' => 'Y-m-d', // Date format
          '#time_format' => 'H:i:s', // Time format
        ];
        $form['execute_submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Execute'),
          '#name' => 'execute',
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'play-button'],
          ],
        ];
        }
      if ($this->getMode() == 'close') {
        $form['stream_end_datetime'] = [
          '#type' => 'datetime',
          '#title' => $this->t('Ending Date/Time'),
          '#default_value' => $this->getStream()->endedAt ? $this->getStream()->endedAt : '',
          '#date_date_element' => 'date', // Use a date element
          '#date_time_element' => 'time', // Use a time element
          '#date_format' => 'Y-m-d', // Date format
          '#time_format' => 'H:i:s', // Time format
        ];
        $form['close_submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Close'),
          '#name' => 'close',
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'close-button'],
          ],
        ];
        }
      $form['cancel_submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Cancel'),
        '#name' => 'back',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'cancel-button'],
        ],
      ];

    // DEPLOYMENT IS INVALID
    } else {
      $form['validation_notification'] = [
        '#type' => 'item',
        '#title' => $this->t('<br><ul><h2>Streamt cannot be executed</h2></ul>'),
      ];
      $form['validation_reason'] = [
        '#type' => 'item',
        '#title' => $this->t('<ul><b>Reason: ' . $validationError . '</b></ul><br>'),
      ];
      $form['cancel_submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Back to Manage Streams'),
        '#name' => 'back',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'back-button'],
        ],
      ];
    }
    $form['bottom_space'] = [
      '#type' => 'item',
      '#title' => t('<br><br>'),
    ];


    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name != 'back') {
      if(strlen($form_state->getValue('stream_name')) < 1) {
        $form_state->setErrorByName('stream_name', $this->t('Please enter a valid name'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      self::backUrl();
      return;
    }

    try{
      $uid = \Drupal::currentUser()->id();
      $useremail = \Drupal::currentUser()->getEmail();

      $orig = $this->getStream();

      $clone = [
        'uri'                       => $this->getStreamUri(),
        'typeUri'                   => HASCO::STREAM,
        'hascoTypeUri'              => HASCO::STREAM,
        'label'                     => $orig->label,
        'comment'                   => $orig->comment,
        'method'                    => $orig->method,
        'messageProtocol'           => $orig->messageProtocol,
        'messageIP'                 => $orig->messageIP,
        'messagePort'               => $orig->messagePort,
        'messageArchiveId'          => $orig->messageArchiveId,
        'canUpdate'                 => $orig->canUpdate,
        'designedAt'                => $orig->designedAt,
        'hasVersion'                => $orig->hasVersion,
        'studyUri'                  => $orig->studyUri,
        'semanticDataDictionaryUri' => $orig->semanticDataDictionaryUri,
        'deploymentUri'             => $orig->deploymentUri,
        'triggeringEvent'           => $orig->triggeringEvent,
        'numberDataPoints'          => $orig->numberDataPoints,
        'datasetPattern'            => $orig->datasetPattern,
        'datasetUri'                => $orig->datasetUri,
      ];

      if ($this->getMode() === 'execute') {
        $clone['startedAt']         = $form_state->getValue('stream_start_datetime')->format('Y-m-d\TH:i:s.v');
        $clone['hasStreamStatus']   = HASCO::ACTIVE;
        $clone['hasMessageStatus']  = HASCO::SUSPENDED;
      }
      elseif ($this->getMode() === 'close') {
        $clone['startedAt']         = $orig->startedAt;
        $clone['endedAt']           = $form_state->getValue('stream_end_datetime')->format('Y-m-d\TH:i:s.v');
        $clone['hasStreamStatus']   = HASCO::CLOSED;
        $clone['hasMessageStatus']  = HASCO::INACTIVE;
      }

      $streamJson = json_encode($clone, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

      $api = \Drupal::service('rep.api_connector');
      $api->elementDel('stream', $this->getStreamUri());
      $api->elementAdd('stream', $streamJson);

      // RENAME to execute because new end-points are still not working
      if ($this->getMode() === 'NEWexecute' && $this->getStream()->method === 'files') {

        $useremail = \Drupal::currentUser()->getEmail();
        $streamUri = $this->getStreamUri();
        $studyUri  = $this->getStream()->studyUri;
        $pattern   = $this->getStream()->datasetPattern;

        // 2.1) Get all Data Acquisitions (DAs) from the study:
        $response = $api->getDataAcquisitionByStudy($studyUri);
        $data = $api->parseObjectResponse($response, 'getDataAcquisitionByStudy');
        // Assuming $data->body is an array of “DataAcquisition” objects
        $allDAs = is_array($data->body) ? $data->body : [];

        foreach ($allDAs as $da) {
          // 2.2) Only interested in those whose streamUri is still empty/null:
          if (empty($da->streamUri)) {
            // 2.3) We need the filename: let's fetch the DataFile for this DA
            $dfResponse = $api->getUri($da->hasDataFileUri);
            $dfData = $api->parseObjectResponse($dfResponse, 'getUri');
            $datafileObj = $dfData->body;

            $filename = $datafileObj->filename;
            // 2.4) If the filename matches (at the beginning) the datasetPattern, then recycle:
            if (preg_match('/^' . $pattern . '/', $filename)) {
              // Store some old values to copy
              $oldDataFileUri = $da->hasDataFileUri;
              $oldDAUri       = $da->uri;
              $oldFileId      = $datafileObj->id;

              // 2.5) Delete the original DA and DataFile:
              $api->elementDel('da', $oldDAUri);
              $api->elementDel('datafile', $oldDataFileUri);

              // 2.6) Recreate a new DataFile with the same name and id, but linking to the current stream:
              //     (this is exactly the snippet you already sent, just adapted for these variables):
              $newDataFileUri = Utils::uriGen('datafile');
              $datafileArr = [
                'uri'                => $newDataFileUri,
                'typeUri'            => HASCO::DATAFILE,
                'hascoTypeUri'       => HASCO::DATAFILE,
                'label'              => $filename,
                'filename'           => $filename,
                'id'                 => $oldFileId,
                'studyUri'           => $studyUri,
                'streamUri'          => $streamUri,
                'fileStatus'         => Constant::FILE_STATUS_UNPROCESSED,
                'hasSIRManagerEmail' => $useremail,
              ];
              $datafileJSON = json_encode($datafileArr);
              $api->datafileAdd($datafileJSON);

              // 2.7) Recreate the Data Acquisition (DA) pointing to the new DataFile:
              $newMTUri = str_replace("DFL", Utils::elementPrefix('da'), $newDataFileUri);
              $mtArr = [
                'uri'               => $newMTUri,
                'typeUri'           => HASCO::DATA_ACQUISITION,
                'hascoTypeUri'      => HASCO::DATA_ACQUISITION,
                'isMemberOfUri'     => $studyUri,
                'label'             => $filename,
                'hasDataFileUri'    => $newDataFileUri,
                'hasVersion'        => '',
                'comment'           => '',
                'hasSIRManagerEmail'=> $useremail,
              ];
              $mtJSON = json_encode($mtArr);
              $api->elementAdd('da', $mtJSON);
            }
          }
        }
      }

      \Drupal::messenger()->addMessage(t("Stream has been updated successfully."));
      self::backUrl();
      return;

    } catch(\Exception $e) {
      \Drupal::messenger()->addError(t("An error occurred while updating the Stream: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  function backUrl() {
    $route_name = 'dpl.manage_streams_route';
    $route_params = [
      'deploymenturi' => base64_encode($this->getDeployment()->uri),
      'state'         => 'active',
      'page'          => '1',
      'pagesize'      => '10',
    ];
    // cria a URL de rota já com parâmetros e converte em string
    $url = Url::fromRoute($route_name, $route_params)->toString();

    $response = new RedirectResponse($url);
    $response->send();

    return;
  }


}
