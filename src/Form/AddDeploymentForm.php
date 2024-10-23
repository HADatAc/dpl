<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\VSTOI;

class AddDeploymentForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_deployment_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    //$form['deployment_name'] = [
    //  '#type' => 'textfield',
    //  '#title' => $this->t('Name'),
    //];
    $form['deployment_platform_instance'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Platform Instance'),
      '#autocomplete_route_name' => 'dpl.platforminstance_autocomplete',
    ];
    $form['deployment_instrument_instance'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Instrument Instance'),
      '#autocomplete_route_name' => 'dpl.instrumentinstance_autocomplete',
    ];
    //$form['deployment_detector_instance'] = [
    //  '#type' => 'textfield',
    //  '#title' => $this->t('Detector Instance'),
    //  '#autocomplete_route_name' => 'dpl.detectorinstance_autocomplete',
    //];
    $form['deployment_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
    ];
    $form['deployment_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
    ];
    $form['save_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#name' => 'save',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'save-button'],
      ],
    ];
    $form['cancel_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#name' => 'back',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'cancel-button'],
      ],
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
    //$detectorUri = '';
    //if ($form_state->getValue('deployment_detector_instance') != NULL && $form_state->getValue('deployment_detector_instance') != '') {
    //  $detectorUri = Utils::uriFromAutocomplete($form_state->getValue('deployment_detector_instance'));
    //}

    $finalLabel = 'a deployment';
    if ($platformInstanceName == '' && $instrumentInstanceName != '') {
      $finalLabel = 'a deployment with instrument ' . $instrumentInstanceName;
    } else if ($platformInstanceName != '' && $instrumentInstanceName == '') {
      $finalLabel = 'a deployment @ ' . $platformInstanceName;
    } else if ($platformInstanceName != '' && $instrumentInstanceName != '') {
      $finalLabel = $instrumentInstanceName . ' @ ' . $platforminstanceName;
    }

    $dateTime = new \DateTime();
    $formattedNow = $dateTime->format('Y-m-d\TH:i:s') . '.' . $dateTime->format('v') . $dateTime->format('O');

    try{
      $useremail = \Drupal::currentUser()->getEmail();
      $newDeploymentUri = Utils::uriGen('deployment');
      $deploymentJson = '{"uri":"'.$newDeploymentUri.'",'.
        '"typeUri":"'.VSTOI::DEPLOYMENT.'",'.
        '"hascoTypeUri":"'.VSTOI::DEPLOYMENT.'",'.
        '"label":"'.$finalLabel.'",'.
        '"hasVersion":"'.$form_state->getValue('deployment_version').'",'.
        '"comment":"'.$form_state->getValue('deployment_description').'",'.
        '"platformInstanceUri":"'.$platformInstanceUri.'",'.
        '"instrumentInstanceUri":"'.$instrumentInstanceUri.'",'.
        //'"detectorInstanceUri":"'.$detectorInstanceUri.'",'.
        '"canUpdate":["'.$useremail.'"],'.
        '"designedAt":"'.$formattedNow.'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      $api = \Drupal::service('rep.api_connector');
      $api->elementAdd('deployment',$deploymentJson);
      \Drupal::messenger()->addMessage(t("Deployment has been added successfully."));
      self::backUrl();
      return;
    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding deployment: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'dpl.add_deployment');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

}
