<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\VSTOI;

class EditPlatformForm extends FormBase {

  protected $platformUri;

  protected $platform;

  public function getPlatformUri() {
    return $this->platformUri;
  }

  public function setPlatformUri($uri) {
    return $this->platformUri = $uri;
  }

  public function getPlatform() {
    return $this->platform;
  }

  public function setPlatform($platform) {
    return $this->platform = $platform;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_platform_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $platformuri = NULL) {
    $uri=$platformuri;
    $uri_decode=base64_decode($uri);
    $this->setPlatformUri($uri_decode);

    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($this->getPlatformUri());
    $obj = json_decode($rawresponse);

    if ($obj->isSuccessful) {
      $this->setPlatform($obj->body);
    } else {
      \Drupal::messenger()->addError(t("Failed to retrieve Platform."));
      self::backUrl();
      return;
    }

    $form['platform_type'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-3 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Platform Type'),
        '#name' => 'platform_type',
        '#default_value' => Utils::fieldToAutocomplete($this->getPlatform()->typeUri, $this->getPlatform()->label),
        '#id' => 'platform_type',
        '#parents' => ['platform_type'],
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'platform',
          ], ['query' => ['field_id' => 'platform_type']])->toString(),
          'data-field-id' => 'platform_type',
          'data-elementtype' => 'platform',
          'autocomplete' => 'off',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
    ];

    $form['platform_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getPlatform()->label,
    ];
    $form['platform_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $this->getPlatform()->hasVersion ?? 1,
      '#disabled' => true
    ];
    $form['platform_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getPlatform()->comment,
    ];
    $form['update_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
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

    if ($button_name != 'back') {
      if(strlen($form_state->getValue('platform_name')) < 1) {
        $form_state->setErrorByName('platform_name', $this->t('Please enter a valid name'));
      }
      if(strlen($form_state->getValue('platform_version')) < 1) {
        $form_state->setErrorByName('platform_version', $this->t('Please enter a valid version'));
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

      $platformJson = '{"uri":"'.$this->getPlatformUri().'",'.
        // '"superUri":"'.VSTOI::PLATFORM.'",'.
        '"superUri":"'.Utils::uriFromAutocomplete($form_state->getValue('platform_type')).'",'.
        '"hascoTypeUri":"'.VSTOI::PLATFORM.'",'.
        '"label":"'.$form_state->getValue('platform_name').'",'.
        '"hasVersion":"'.$form_state->getValue('platform_version').'",'.
        '"comment":"'.$form_state->getValue('platform_description').'",'.
        // '"hasStatus":"'.VSTOI::CURRENT.'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      // UPDATE BY DELETING AND CREATING
      $api = \Drupal::service('rep.api_connector');
      $api->elementDel('platform',$this->getPlatformUri());
      $newPlatform = $api->elementAdd('platform',$platformJson);

      \Drupal::messenger()->addMessage(t("Platform has been updated successfully."));
      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating the Platform: ".$e->getMessage()));
      self::backUrl();
      return;
    }

  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'dpl.edit_platform');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }


}
