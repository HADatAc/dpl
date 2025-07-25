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

class ExecuteCloseDeploymentForm extends FormBase {

  protected $mode;

  protected $deployment;

  public function getMode() {
    return $this->mode;
  }
  public function setMode($mode) {
    return $this->mode = $mode;
  }

  public function getDeployment() {
    return $this->deployment;
  }
  public function setDeployment($deployment) {
    return $this->deployment = $deployment;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'execute_close_deployment_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $mode = NULL, $deploymenturi = NULL) {
    $api = \Drupal::service('rep.api_connector');

    // CHECK MODE
    if (($mode == NULL) ||
        ($mode != 'execute' && $mode != 'close')) {
      \Drupal::messenger()->addError(t("Invalid Deployment execute/close operation."));
      self::backUrl();
      return;
    }
    $this->setMode($mode);

    // RETRIEVE DEPLOYMENT
    $uri_decode=base64_decode($deploymenturi);
    $rawresponse = $api->getUri($uri_decode);
    $obj = json_decode($rawresponse);
    if ($obj->isSuccessful) {
      $this->setDeployment($obj->body);
    } else {
      \Drupal::messenger()->addError(t("Failed to retrieve Deployment."));
      self::backUrl();
      return;
    }

    $platformInstanceLabel = ' ';
    if (isset($this->getDeployment()->platformInstance) &&
        isset($this->getDeployment()->platformInstance->uri) &&
        isset($this->getDeployment()->platformInstance->label)) {
      $platformInstanceLabel = Utils::fieldToAutocomplete(
        $this->getDeployment()->platformInstance->uri,
        $this->getDeployment()->platformInstance->label
      );
    }
    $instrumentInstanceLabel = ' ';
    if (isset($this->getDeployment()->instrumentInstance) &&
        isset($this->getDeployment()->instrumentInstance->uri) &&
        isset($this->getDeployment()->instrumentInstance->label)) {
      $instrumentInstanceLabel = Utils::fieldToAutocomplete(
        $this->getDeployment()->instrumentInstance->uri,
        $this->getDeployment()->instrumentInstance->label
      );
    }

    $validationError = NULL;
    if (!isset($this->getDeployment()->platformInstance) && !isset($this->getDeployment()->instrumentInstance)) {
      $validationError = "Deployment is missing both PLATFORM instance and INSTRUMENT instance.";
    }
    if (!isset($this->getDeployment()->platformInstance) && isset($this->getDeployment()->instrumentInstance)) {
      $validationError = "Deployment is missing associated PLATFORM instance.";
    }
    if (isset($this->getDeployment()->platformInstance) && !isset($this->getDeployment()->instrumentInstance)) {
      $validationError = "Deployment is missing associated INSTRUMENT instance.";
    }

    //dpm($this->getDeployment());

    if ($this->getMode() == 'execute') {
      $form['page_title'] = [
        '#type' => 'item',
        '#title' => $this->t('<h3>Execute Deployment</h3>'),
      ];
    }
    if ($this->getMode() == 'close') {
      $form['page_title'] = [
        '#type' => 'item',
        '#title' => $this->t('<h3>Close Deployment</h3>'),
      ];
    }
    $form['deployment_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getDeployment()->label,
      '#disabled' => TRUE,
    ];
    $form['deployment_platform_instance'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Platform Instance'),
      '#default_value' => $platformInstanceLabel,
      '#disabled' => TRUE,
    ];
    $form['deployment_instrument_instance'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Instrument Instance'),
      '#default_value' => $instrumentInstanceLabel,
      '#disabled' => TRUE,
    ];

    // DEPLOYMENT IS VALID
    if ($validationError == NULL) {
      if ($this->getMode() == 'execute') {
        $form['deployment_start_datetime'] = [
          '#type' => 'datetime',
          '#title' => $this->t('Starting Date/Time'),
          '#default_value' => $this->getDeployment()->startedAt ? $this->getDeployment()->startedAt : '',
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
        $form['deployment_end_datetime'] = [
          '#type' => 'datetime',
          '#title' => $this->t('Ending Date/Time'),
          '#default_value' => $this->getDeployment()->endedAt ? $this->getDeployment()->endedAt : '',
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
        '#title' => $this->t('<br><ul><h2>Deployment cannot be executed</h2></ul>'),
      ];
      $form['validation_reason'] = [
        '#type' => 'item',
        '#title' => $this->t('<ul><b>Reason: ' . $validationError . '</b></ul><br>'),
      ];
      $form['cancel_submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Back to Manage Deployments'),
        '#name' => 'back',
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
      if(strlen($form_state->getValue('deployment_name')) < 1) {
        $form_state->setErrorByName('deployment_name', $this->t('Please enter a valid name'));
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
      $api = \Drupal::service('rep.api_connector');
      $uid = \Drupal::currentUser()->id();
      $useremail = \Drupal::currentUser()->getEmail();

      // DEPLOYMENT RELATED
      $deploymentJson = '{"uri":"'.$this->getDeployment()->uri.'",'.
        '"typeUri":"'.VSTOI::DEPLOYMENT.'",'.
        '"hascoTypeUri":"'.VSTOI::DEPLOYMENT.'",'.
        '"label":"'.$this->getDeployment()->label.'",'.
        '"hasVersion":"'.$this->getDeployment()->hasVersion.'",'.
        '"comment":"'.$this->getDeployment()->comment.'",'.
        '"platformInstanceUri":"'.$this->getDeployment()->platformInstanceUri.'",'.
        '"instrumentInstanceUri":"'.$this->getDeployment()->instrumentInstanceUri.'",'.
        //'"detectorUri":"'.$detectorUri.'",'.
        '"canUpdate":["'.$useremail.'"],'.
        '"designedAt":"'.$this->getDeployment()->designedAt.'",';
      if ($this->getMode() == 'execute') {
        $deploymentJson .=
          '"startedAt":"'.$form_state->getValue('deployment_start_datetime')->format('Y-m-d\TH:i:s.v').'",';
      }
      if ($this->getMode() == 'close') {
        $deploymentJson .=
          '"startedAt":"'.$this->getDeployment()->startedAt.'",'.
          '"endedAt":"'.$form_state->getValue('deployment_end_datetime')->format('Y-m-d\TH:i:s.v').'",';
      }
      $deploymentJson .= '"hasSIRManagerEmail":"'.$useremail.'"}';

      // INSTRUMENT INSTANCE RELATED
      $rawresponse = $api->getUri($this->getDeployment()->instrumentInstanceUri);
      $obj = json_decode($rawresponse);
      if ($obj->isSuccessful) {
        $orig = $obj->body;

        $iiClone = [
          'uri'                 => $orig->uri,
          'typeUri'             => $orig->typeUri,
          'hascoTypeUri'        => $orig->hascoTypeUri,
          'label'               => $orig->label,
          'comment'             => $orig->comment,
          'hasImageUri'         => $orig->hasImageUri,
          'hasWebDocument'      => $orig->hasWebDocument,
          'hasSerialNumber'     => $orig->hasSerialNumber,
          'hasAcquisitionDate'  => $orig->hasAcquisitionDate,
          'isDamaged'           => $orig->isDamaged,
          'hasDamageDate'       => $orig->hasDamageDate,
          'hasOwnerUri'         => $orig->hasOwnerUri,
          'hasMaintainerUri'    => $orig->hasMaintainerUri,
          'hasSIRManagerEmail'  => $orig->hasSIRManagerEmail
        ];

        if ($this->getMode() == 'execute') {
          $iiClone['hasStatus'] = VSTOI::DEPLOYED;
        }

        if ($this->getMode() == 'close') {
          $iiClone['hasStatus'] = VSTOI::CURRENT;
        }

        // UPDATE BY DELETING AND CREATING THE INSTRUMENT INSTANCE
        $api->elementDel('instrumentinstance', $orig->uri);
        $api->elementAdd('instrumentinstance', json_encode($iiClone, JSON_UNESCAPED_SLASHES));

      } else {
        \Drupal::messenger()->addError(t("Failed to Execute, could not retrieve Instrument Instance."));
        self::backUrl();
        return false;
      }

      // UPDATE BY DELETING AND CREATING THE DEPLOYMENT
      $api->elementDel('deployment',$this->getDeployment()->uri);
      $api->elementAdd('deployment',$deploymentJson);

      /* IF CLOSE OPERATION,
            - ALL ACTIVE STREAMS MUST BE CLOSED
            - ALL TOPICS INACTIVATED
         IMPORTANT AND URGENT:
            - WAS WORKING RIGHT NOW IT NOT BECAUSE
            - API MUST IMPLEMENT AGAINT THIS END-POINT
            - WHEN IMPLEMENTE JUST -->>>>>> UN-COMMENT THE NEXT IF
            - IT WILL ALSO INACTIVATE THE "TOPICS", BUT LAKS TESTING!!!!!
      */

      // if ($this->getMode() === 'close') {

      //   // Replace HASCO::ACTIVE and HASCO::STREAM with your constants as needed.
      //   $streamList = $api->parseObjectResponse(
      //     $api->streamByStateEmailDeployment(rawurlencode(HASCO::ACTIVE), $useremail, $this->getDeployment()->uri, 99999, 0),
      //     'streamByStateEmailDeployment'
      //   );

      //   // 2) Loop over each active stream and “close” it:
      //   foreach ($streamList as $stream) {
      //     // a) We use $stream itself as $orig for cloning purposes.
      //     $orig = $stream;

      //     // b) Build a “clone” array. Copy all fields from the original, except set:
      //     //    - endedAt       => the form‐provided end datetime
      //     //    - hasStreamStatus => CLOSED
      //     //    (Adjust any other fields you need to change, if necessary.)
      //     $clone = [
      //       'uri'                       => $orig->uri,
      //       'typeUri'                   => HASCO::STREAM,
      //       'hascoTypeUri'              => HASCO::STREAM,
      //       'label'                     => $orig->label,
      //       'comment'                   => $orig->comment,
      //       'method'                    => $orig->method,
      //       'messageProtocol'           => $orig->messageProtocol,
      //       'messageIP'                 => $orig->messageIP,
      //       'messagePort'               => $orig->messagePort,
      //       'messageArchiveId'          => $orig->messageArchiveId,
      //       'canUpdate'                 => $orig->canUpdate,
      //       'designedAt'                => $orig->designedAt,
      //       'hasVersion'                => $orig->hasVersion,
      //       'studyUri'                  => $orig->studyUri,
      //       'semanticDataDictionaryUri' => $orig->semanticDataDictionaryUri,
      //       'deploymentUri'             => $orig->deploymentUri,
      //       'triggeringEvent'           => $orig->triggeringEvent,
      //       'numberDataPoints'          => $orig->numberDataPoints,
      //       'datasetPattern'            => $orig->datasetPattern,
      //       'datasetUri'                => $orig->datasetUri,
      //       'startedAt'                 => $orig->startedAt,
      //       // Use the form_state value for the new endedAt:
      //       'endedAt'                   => $form_state
      //                                         ->getValue('deployment_end_datetime')
      //                                         ->format('Y-m-d\TH:i:s.v'),
      //       'hasStreamStatus'           => HASCO::CLOSED,
      //     ];

      //     // WE MUST INACTIVATE ALL TOPICS
      //     if (!empty($orig->topics)){
      //       $topicsList = $orig->topics;

      //       foreach ($topicsList as $topicItem) {

      //         $streamTopic = [
      //           'uri'                       => $topicItem->uri,
      //           'typeUri'                   => HASCO::STREAMTOPIC,
      //           'hascoTypeUri'              => HASCO::STREAMTOPIC,
      //           'streamUri'                 => $orig->uri,
      //           'label'                     => $topicItem->label,
      //           'deploymentUri'             => $topicItem->deploymentUri,
      //           'semanticDataDictionaryUri' => $topicItem->semanticDataDictionaryUri,
      //           'cellScopeUri'              => $topicItem->cellScopeUri,
      //           'hasTopicStatus'            => HASCO::INACTIVE,
      //         ];

      //         \Drupal::service('rep.api_connector')->elementDel('streamtopic', $topicItem->uri);
      //         \Drupal::service('rep.api_connector')->elementAdd('streamtopic', json_encode($streamTopic, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

      //       }
      //     }

      //     // c) JSON‐encode without escaping slashes or unicode:
      //     $streamJson = json_encode($clone, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

      //     // d) Delete the old stream and add the “closed” version back:
      //     $api->elementDel('stream', $orig->uri);
      //     $api->elementAdd('stream', $streamJson);

      //   }
      // }

      \Drupal::messenger()->addMessage(t("Deployment has been updated successfully."));
      self::backUrl();
      return;

    } catch(\Exception $e) {
      \Drupal::messenger()->addError(t("An error occurred while updating the Deployment: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  function backUrl() {
    // $uid = \Drupal::currentUser()->id();
    // $previousUrl = Utils::trackingGetPreviousUrl($uid, 'dpl.execute_close_deployment');
    // if ($previousUrl) {
    //   $response = new RedirectResponse($previousUrl);
    //   $response->send();
    //   return;
    // }

    // Change made to after execute a deployment it goes directly to ACTIVE pill.
    $route_name = 'dpl.manage_deployments_route';
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
