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
    return 'execute_close_stream_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $mode = NULL, $deploymenturi = NULL) {

    if (($mode == NULL) ||
        ($mode != 'execute' && $mode != 'close')) {
      \Drupal::messenger()->addError(t("Invalid Deployment execute/close operation."));
      self::backUrl();
      return;
    }
    $this->setMode($mode);

    $uri=$deploymenturi;
    $uri_decode=base64_decode($uri);
    $this->setDeploymentUri($uri_decode);

    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($this->getDeploymentUri());
    $obj = json_decode($rawresponse);

    if ($obj->isSuccessful) {
      $this->setDeployment($obj->body);
    } else {
      \Drupal::messenger()->addError(t("Failed to retrieve Deployment."));
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
      '#default_value' => $platformLabel,
      '#disabled' => TRUE,
    ];
    $form['deployment_instrument_instance'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Instrument Instance'),
      '#default_value' => $instrumentLabel,
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
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'dpl.execute_close_deployment');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }


}
