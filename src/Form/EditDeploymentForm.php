<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\VSTOI;

class EditDeploymentForm extends FormBase {

  protected $deployment;

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
    return 'edit_deployment_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $deploymenturi = NULL) {
    $api = \Drupal::service('rep.api_connector');

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

    //dpm($this->getDeployment());

    $form['deployment_platform_instance'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Platform Instance'),
      '#default_value' => $platformInstanceLabel,
      '#autocomplete_route_name' => 'dpl.platforminstance_autocomplete',
    ];
    $form['deployment_instrument_instance'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Instrument Instance'),
      '#default_value' => $instrumentInstanceLabel,
      '#autocomplete_route_name' => 'dpl.instrumentinstance_autocomplete',
    ];
    $form['deployment_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $this->getDeployment()->hasVersion,
    ];
    $form['deployment_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getDeployment()->comment,
    ];
    $form['update_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
      '#name' => 'save',
    ];
    $form['cancel_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#name' => 'back',
    ];
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

    //if ($button_name != 'back') {
    //  if(strlen($form_state->getValue('deployment_name')) < 1) {
    //    $form_state->setErrorByName('deployment_name', $this->t('Please enter a valid name'));
    //  }
    //}
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

    $platformInstanceUri = '';
    $platformInstanceName = '';
    if ($form_state->getValue('deployment_platform_instance') != NULL && $form_state->getValue('deployment_platform_instance') != '') {
      $platformInstanceUri = Utils::uriFromAutocomplete($form_state->getValue('deployment_platform_instance'));
      $platformInstanceName = Utils::labelFromAutocomplete($form_state->getValue('deployment_platform_instance'));
    } 
    $instrumentInstanceUri = '';
    $instrumentInstanceName = '';
    if ($form_state->getValue('deployment_instrument_instance') != NULL && $form_state->getValue('deployment_instrument_instance') != '') {
      $instrumentInstanceUri = Utils::uriFromAutocomplete($form_state->getValue('deployment_instrument_instance'));
      $instrumentInstanceName = Utils::labelFromAutocomplete($form_state->getValue('deployment_instrument_instance'));
    } 

    $finalLabel = 'a deployment';
    if ($platformInstanceName == '' && $instrumentInstanceName != '') {
      $finalLabel = 'a deployment with instrument ' . $instrumentInstanceName;
    } else if ($platformInstanceName != '' && $instrumentInstanceName == '') {
      $finalLabel = 'a deployment @ ' . $platformInstanceName;
    } else if ($platformInstanceName != '' && $instrumentInstanceName != '') {
      $finalLabel = $instrumentInstanceName . ' @ ' . $platformInstanceName;
    }

    try{
      $uid = \Drupal::currentUser()->id();
      $useremail = \Drupal::currentUser()->getEmail();

      $deploymentJson = '{"uri":"'.$this->getDeployment()->uri.'",'.
        '"typeUri":"'.VSTOI::DEPLOYMENT.'",'.
        '"hascoTypeUri":"'.VSTOI::DEPLOYMENT.'",'.
        '"label":"'.$finalLabel.'",'.
        '"hasVersion":"'.$form_state->getValue('deployment_version').'",'.
        '"comment":"'.$form_state->getValue('deployment_description').'",'.
        '"platformInstanceUri":"'.$platformInstanceUri.'",'.
        '"instrumentInstanceUri":"'.$instrumentInstanceUri.'",'.
        '"canUpdate":["'.$useremail.'"],'.
        '"designedAt":"'.$this->getDeployment()->designedAt.'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';
  
      // UPDATE BY DELETING AND CREATING
      $api = \Drupal::service('rep.api_connector');
      $api->elementDel('deployment',$this->getDeployment()->uri);
      $newDeployment = $api->elementAdd('deployment',$deploymentJson);
    
      \Drupal::messenger()->addMessage(t("Deployment has been updated successfully."));
      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating the Deployment: ".$e->getMessage()));
      self::backUrl();
      return;
    }

  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'dpl.edit_deployment');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }
  

}