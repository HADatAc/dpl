<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\VSTOI;

class EditInstanceForm extends FormBase {

  protected $element;

  protected $elementType;

  protected $elementName;

  public function getElement() {
    return $this->element;
  }

  public function setElement($element) {
    return $this->element = $element;
  }

  public function getElementType() {
    return $this->elementType;
  }

  public function setElementType($elementType) {
    return $this->elementType = $elementType;
  }

  public function getElementName() {
    return $this->elementName;
  }

  public function setElementName($elementName) {
    return $this->elementName = $elementName;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_instance_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $instanceuri = NULL) {

    // Does the repo have a social network?
    $socialEnabled = \Drupal::config('rep.settings')->get('social_conf');

    if ($instanceuri == NULL || $instanceuri == "") {
      \Drupal::messenger()->addError(t("No element uri has been provided"));
      self::backUrl();
      return;
    }

    $uri_decode=base64_decode($instanceuri);
    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($uri_decode);
    $obj = json_decode($rawresponse);
    if ($obj->isSuccessful) {
      $this->setElement($obj->body);
    } else {
      \Drupal::messenger()->addError(t("Failed to retrieve element with URI [" + $uri_decode + "]."));
      self::backUrl();
      return;
    }

    $this->setElementName(NULL);
    $autocomplete = '';
    if ($this->getElement()->hascoTypeUri == VSTOI::PLATFORM_INSTANCE) {
      $this->setElementName("Platform Instance");
      $this->setElementType("platforminstance");
      $autocomplete = 'dpl.platform_autocomplete';
    } else if ($this->getElement()->hascoTypeUri == VSTOI::INSTRUMENT_INSTANCE) {
      $this->setElementName("Instrument Instance");
      $this->setElementType("instrumentinstance");
      $autocomplete = 'dpl.instrument_autocomplete';
    } else if ($this->getElement()->hascoTypeUri == VSTOI::DETECTOR_INSTANCE) {
      $this->setElementName("Detector Instance");
      $this->setElementType("detectorinstance");
      $autocomplete = 'dpl.detector_autocomplete';
    } else if ($this->getElement()->hascoTypeUri == VSTOI::ACTUATOR_INSTANCE) {
      $this->setElementName("Actuator Instance");
      $this->setElementType("actuatorinstance");
      $autocomplete = 'dpl.actuator_autocomplete';
    }

    if ($this->getElementName() == NULL) {
      \Drupal::messenger()->addError(t("No VALID element type has been provided "));
      self::backUrl();
      return;
    }

    $typeLabel = '';
    if ($this->getElement()->type != NULL &&
        $this->getElement()->type->label != NULL &&
        $this->getElement()->type->uri != NULL ) {
      $typeLabel = $this->getElement()->type->label . ' [' . $this->getElement()->type->uri . ']';
    }

    $form['page_title'] = [
      '#type' => 'item',
      '#title' => $this->t('<h3>Edit ' . $this->getElementName() . '</h3>'),
    ];
    $form['instance_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Type'),
      '#autocomplete_route_name' => $autocomplete,
      '#default_value' => $typeLabel,
  ];
    $form['instance_serial_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Serial Number'),
      '#default_value' => $this->getElement()->hasSerialNumber,
    ];
    $form['instance_acquisition_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Acquisition Date'),
      '#default_value' => $this->getElement()->hasAcquisitionDate,
    ];
    if ($socialEnabled) {
      $api = \Drupal::service('rep.api_connector');
      $ownerUri = $api->getUri($this->getElement()->hasOwnerUri);
      $maintainerUri = $api->getUri($this->getElement()->hasMaintainerUri);
      $form['instance_owner'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Owner'),
        '#default_value' => isset($this->getElement()->hasOwnerUri) ?
                              Utils::fieldToAutocomplete($this->getElement()->hasOwnerUri, $ownerUri->label) : '',
        // '#required' => TRUE,
        '#autocomplete_route_name'       => 'rep.social_autocomplete',
        '#autocomplete_route_parameters' => [
          'entityType' => 'organization',
        ],
      ];
      $form['instance_maintainer'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Maintainer'),
        '#default_value' => isset($this->getElement()->hasMaintainerUri) ?
                              Utils::fieldToAutocomplete($this->getElement()->hasMaintainerUri, $maintainerUri->label) : '',
        // '#required' => TRUE,
        '#autocomplete_route_name'       => 'rep.social_autocomplete',
        '#autocomplete_route_parameters' => [
          'entityType' => 'person',
        ],
      ];
    }
    $form['instance_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getElement()->comment,
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

    if ($button_name != 'back') {
      if(strlen($form_state->getValue('instance_type')) < 1) {
        $form_state->setErrorByName('instance_type', $this->t('Please enter a valid name'));
      }
      if(strlen($form_state->getValue('instance_serial_number')) < 1) {
        $form_state->setErrorByName('instance_number', $this->t('Please enter a valid name'));
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

    $typeUri = '';
    if ($form_state->getValue('instance_type') != NULL && $form_state->getValue('instance_type') != '') {
      $typeUri = Utils::uriFromAutocomplete($form_state->getValue('instance_type'));
    }

    $hascoTypeUri = '';
    if ($this->getElement()->hascoTypeUri != NULL) {
      $hascoTypeUri = $this->getElement()->hascoTypeUri;
    }

    $label = "Instance of [" . Utils::labelFromAutocomplete($form_state->getValue('instance_type')) . "] with Serial Number: [" . $form_state->getValue('instance_serial_number') . "].";
    // $label = Utils::labelFromAutocomplete($form_state->getValue('instance_type')) . " with ID# " . $form_state->getValue('instance_serial_number');

    try{
      $useremail = \Drupal::currentUser()->getEmail();
      // $newInstanceUri = Utils::uriGen($this->getElementType());
      $instanceJson = '{"uri":"'.$this->getElement()->uri.'",' .
        '"typeUri":"'.$typeUri.'",'.
        '"hascoTypeUri":"'.$hascoTypeUri.'",'.
        '"hasStatus":"' . VSTOI::DRAFT . '",' .
        '"label":"'.$label.'",'.
        '"hasSerialNumber":"'.$form_state->getValue('instance_serial_number').'",'.
        '"comment":"'.$form_state->getValue('instance_description').'",'.
        '"hasOwnerUri":"'.Utils::uriFromAutocomplete($form_state->getValue('instance_owner')).'",'.
        '"hasMaintainerUri":"'.Utils::uriFromAutocomplete($form_state->getValue('instance_maintainer')).'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      $api = \Drupal::service('rep.api_connector');
      $api->elementAdd($this->getElementType(),$instanceJson);
      \Drupal::messenger()->addMessage(t($this->getElementName() . " has been added successfully."));
      self::backUrl();
      return;
    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding stream: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'dpl.edit_instance');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

}
