<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\VSTOI;

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
      $uid = \Drupal::currentUser()->id();
      $useremail = \Drupal::currentUser()->getEmail();

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

      // dpm($deploymentJson);return false;

      // UPDATE BY DELETING AND CREATING
      $api = \Drupal::service('rep.api_connector');
      $api->elementDel('deployment',$this->getDeployment()->uri);
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
  }

}
