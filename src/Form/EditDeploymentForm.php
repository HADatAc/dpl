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

  protected $deploymentUri;

  protected $deployment;

  public function getDeploymentUri() {
    return $this->deploymentUri;
  }

  public function setDeploymentUri($uri) {
    return $this->deploymentUri = $uri; 
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
    return 'edit_deployment_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $deploymenturi = NULL) {
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

    //dpm($this->getDeployment());

    $form['deployment_platform_instance'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Platform Instance'),
      '#default_value' => $platformLabel,
      '#autocomplete_route_name' => 'dpl.platforminstance_autocomplete',
    ];
    $form['deployment_instrument_instance'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Instrument Instance'),
      '#default_value' => $instrumentLabel,
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

    $platformUri = '';
    $platformName = '';
    if ($form_state->getValue('deployment_platform_instance') != NULL && $form_state->getValue('deployment_platform_instance') != '') {
      $platformUri = Utils::uriFromAutocomplete($form_state->getValue('deployment_platform_instance'));
      $platformName = Utils::labelFromAutocomplete($form_state->getValue('deployment_platform_instance'));
    } 
    $instrumentUri = '';
    $instrumentName = '';
    if ($form_state->getValue('deployment_instrument_instance') != NULL && $form_state->getValue('deployment_instrument_instance') != '') {
      $instrumentUri = Utils::uriFromAutocomplete($form_state->getValue('deployment_instrument_instance'));
      $instrumentName = Utils::labelFromAutocomplete($form_state->getValue('deployment_instrument_instance'));
    } 

    $finalLabel = 'a deployment';
    if ($platformName == '' && $instrumentName != '') {
      $finalLabel = 'a deployment with instrument ' . $instrumentName;
    } else if ($platformName != '' && $instrumentName == '') {
      $finalLabel = 'a deployment @ ' . $platformName;
    } else if ($platformName != '' && $instrumentName != '') {
      $finalLabel = $instrumentName . ' @ ' . $platformName;
    }

    try{
      $uid = \Drupal::currentUser()->id();
      $useremail = \Drupal::currentUser()->getEmail();

      $deploymentJson = '{"uri":"'.$this->getDeploymentUri().'",'.
        '"typeUri":"'.VSTOI::DEPLOYMENT.'",'.
        '"hascoTypeUri":"'.VSTOI::DEPLOYMENT.'",'.
        '"label":"'.$finalLabel.'",'.
        '"hasVersion":"'.$form_state->getValue('deployment_version').'",'.
        '"comment":"'.$form_state->getValue('deployment_description').'",'.
        '"platformUri":"'.$platformUri.'",'.
        '"instrumentUri":"'.$instrumentUri.'",'.
        '"canUpdate":["'.$useremail.'"],'.
        '"designedAt":"'.$this->getDeployment()->designedAt.'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';
  
      // UPDATE BY DELETING AND CREATING
      $api = \Drupal::service('rep.api_connector');
      $api->elementDel('deployment',$this->getDeploymentUri());
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