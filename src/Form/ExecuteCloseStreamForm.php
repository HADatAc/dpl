<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\VSTOI;

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

    kint([
      'Stream' => $this->getStream(),
      'Deployment' => $this->getDeployment(),
    ]);

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

      $deploymentJson = '{"uri":"'.$this->getDeploymentUri().'",'.
        '"typeUri":"'.VSTOI::DEPLOYMENT.'",'.
        '"hascoTypeUri":"'.VSTOI::DEPLOYMENT.'",'.
        '"label":"'.$this->getDeployment()->label.'",'.
        '"hasVersion":"'.$this->getDeployment()->hasVersion.'",'.
        '"comment":"'.$this->getDeployment()->comment.'",'.
        '"platformUri":"'.$this->getDeployment()->platformUri.'",'.
        '"instrumentUri":"'.$this->getDeployment()->instrumentUri.'",'.
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

      //$updatedDeployment = clone $this->getDeployment();
      //$deploymentJson = json_encode($updatedDeployment);

      //dpm($deploymentJson);

      // UPDATE BY DELETING AND CREATING
      $api = \Drupal::service('rep.api_connector');
      $api->elementDel('deployment',$this->getDeploymentUri());
      $api->elementAdd('deployment',$deploymentJson);

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
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'dpl.execute_close_stream');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }


}
