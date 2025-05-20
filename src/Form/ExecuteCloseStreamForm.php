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

  public function getStreamUri() {
    return $this->streamUri;
  }
  public function setStreamUri($uri) {
    return $this->streamUri = $uri;
  }

  public function getStream() {
    return $this->stream;
  }
  public function setStream($stream) {
    return $this->deployment = $stream;
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
  public function buildForm(array $form, FormStateInterface $form_state, $mode = NULL, $streamUri = NULL) {

    if (($mode == NULL) ||
        ($mode != 'execute' && $mode != 'close')) {
      \Drupal::messenger()->addError(t("Invalid Deployment execute/close operation."));
      self::backUrl();
      return;
    }
    $this->setMode($mode);

    $uri=$streamUri;
    $uri_decode=base64_decode($uri);
    $this->setStreamUri($uri_decode);

    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($this->getStreamUri());
    $obj = json_decode($rawresponse);

    if ($obj->isSuccessful) {
      $this->setStreamUri($obj->body);
    } else {
      \Drupal::messenger()->addError(t("Failed to retrieve Stream."));
      self::backUrl();
      return;
    }

    $platformLabel = ' ';
    if (isset($this->getDeployment()->platform) &&
        isset($this->getDeployment()->platform->uri) &&
        isset($this->getDeployment()->platform->label)) {
      $platformLabel = Utils::fieldToAutocomplete(
        $this->getDeployment()->platform->uri,
        $this->getDeployment()->platform->label
      );
    }
    $instrumentLabel = ' ';
    if (isset($this->getDeployment()->platform) &&
        isset($this->getDeployment()->platform->uri) &&
        isset($this->getDeployment()->platform->label)) {
      $instrumentLabel = Utils::fieldToAutocomplete(
        $this->getDeployment()->instrument->uri,
        $this->getDeployment()->instrument->label
      );
    }

    $validationError = NULL;
    if (!isset($this->getDeployment()->platform) && !isset($this->getDeployment()->instrument)) {
      $validationError = "Deployment is missing both PLATFORM instance and INSTRUMENT instance.";
    }
    if (!isset($this->getDeployment()->platform) && isset($this->getDeployment()->instrument)) {
      $validationError = "Deployment is missing associated PLATFORM instance.";
    }
    if (isset($this->getDeployment()->platform) && !isset($this->getDeployment()->instrument)) {
      $validationError = "Deployment is missing associated INSTRUMENT instance.";
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
        '#title' => $this->t('<h3>Close Stream</h3>'),
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
      '#default_value' => $platformLabel,
      '#disabled' => TRUE,
    ];
    $form['deployment_instrument_instance'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Instrument Instance'),
      '#default_value' => $instrumentLabel,
      '#disabled' => TRUE,
    ];

    // STREAM IS VALID
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

    // STREAM IS INVALID
    } else {
      $form['validation_notification'] = [
        '#type' => 'item',
        '#title' => $this->t('<br><ul><h2>Stream cannot be executed</h2></ul>'),
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
      $uid = \Drupal::currentUser()->id();
      $useremail = \Drupal::currentUser()->getEmail();

      // $deploymentJson = '{"uri":"'.$this->getDeploymentUri().'",'.
      //   '"typeUri":"'.VSTOI::DEPLOYMENT.'",'.
      //   '"hascoTypeUri":"'.VSTOI::DEPLOYMENT.'",'.
      //   '"label":"'.$this->getDeployment()->label.'",'.
      //   '"hasVersion":"'.$this->getDeployment()->hasVersion.'",'.
      //   '"comment":"'.$this->getDeployment()->comment.'",'.
      //   '"platformUri":"'.$this->getDeployment()->platformUri.'",'.
      //   '"instrumentUri":"'.$this->getDeployment()->instrumentUri.'",'.
      //   //'"detectorUri":"'.$detectorUri.'",'.
      //   '"canUpdate":["'.$useremail.'"],'.
      //   '"designedAt":"'.$this->getDeployment()->designedAt.'",';
      $streamJson = '{"uri":"'.$this->getStreamUri().'",'.
        '"typeUri":"'.HASCO::STREAM.'",'.
        '"hascoTypeUri":"'.HASCO::STREAM.'",'.
        '"label":"'.$this->getStream()->label.'",'.
        '"method":"'.$this->getStream()->method.'",'.
        '"deploymentUri":"'.$this->getStream()->deploymentUri.'",'.
        '"hasVersion":"'.$this->getStream()->hasVersion.'",'.
        '"comment":"'.$this->getStream()->comment.'",'.
        '"messageProtocol":"'.$this->getStream()->messageProtocol.'",'.
        '"messageIP":"'.$this->getStream()->messageIP.'",'.
        '"messagePort":"'.$this->getStream()->messagePort.'",'.
        '"messageArchiveId":"'.$this->getStream()->messageArchiveId.'",'.
        '"canUpdate":["'.$useremail.'"],'.
        '"designedAt":"'.$this->getStream()->designedAt.'",'.
        '"studyUri":"'.$this->getStream()->studyUri.'",'.
        '"semanticDataDictionaryUri":"'.$this->getStream()->semanticDataDictionaryUri.'",';
        // '"hasStreamStatus":"' . HASCO::DRAFT.'",'.

        if ($this->getMode() == 'execute') {
          $streamJson .=
            '"startedAt":"'.$this->getStream()->startedAt.'",';
        }
        if ($this->getMode() == 'close') {
          $streamJson .=
            '"startedAt":"'.$this->getStream()->startedAt.'",'.
            '"endedAt":"'.$form_state->getValue('stream_end_datetime')->format('Y-m-d\TH:i:s.v').'",';
        }

      $streamJson .= '"hasSIRManagerEmail":"'.$useremail.'"}';

      //$updatedDeployment = clone $this->getDeployment();
      //$deploymentJson = json_encode($updatedDeployment);

      //dpm($deploymentJson);

      // UPDATE BY DELETING AND CREATING
      $api = \Drupal::service('rep.api_connector');
      $api->elementDel('stream',$this->getStreamUri());
      $api->elementAdd('stream',$streamJson);

      \Drupal::messenger()->addMessage(t("Stream has been updated successfully."));
      self::backUrl();
      return;

    } catch(\Exception $e) {
      \Drupal::messenger()->addError(t("An error occurred while updating the Deployment: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'dpl.execute_close_deployment');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }


}
