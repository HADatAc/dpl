<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\HASCO;
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
    $preferred_instrument = \Drupal::config('rep.settings')->get('preferred_instrument') ?? 'instrument';
    $preferred_component = \Drupal::config('rep.settings')->get('preferred_component') ?? 'component';
    $preferred_platform = \Drupal::config('rep.settings')->get('preferred_platform') ?? 'platform';

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

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
    if ($this->getElement()->hascoTypeUri == HASCO::PLATFORM_INSTANCE) {
      $this->setElementName(ucfirst($preferred_platform) . " Instance");
      $this->setElementType("platforminstance");
      $autocomplete = 'dpl.platform_autocomplete';
      $treepath = 'platform';
      $treename = ucfirst($preferred_platform);
    } else if ($this->getElement()->hascoTypeUri == HASCO::INSTRUMENT_INSTANCE) {
      $this->setElementName(ucfirst($preferred_instrument)." Instance");
      $this->setElementType("instrumentinstance");
      $autocomplete = 'dpl.instrument_autocomplete';
      $treepath = 'instrument';
      $treename = ucfirst($preferred_instrument);
    } else if ($this->getElement()->hascoTypeUri == HASCO::COMPONENT_INSTANCE) {
      $this->setElementName(ucfirst($preferred_component)." Instance");
      $this->setElementType("componentinstance");
      $autocomplete = 'dpl.component_autocomplete';
      $treepath = 'component';
      $treename = ucfirst($preferred_component);
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
    // $form['instance_type'] = [
    //   '#type' => 'textfield',
    //   '#title' => $this->t('Type'),
    //   '#autocomplete_route_name' => $autocomplete,
    //   '#default_value' => $typeLabel,
    // ];
    $form['instance_type'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-3 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $treename,
        '#name' => 'instance_type',
        '#default_value' => Utils::fieldToAutocomplete($this->getElement()->typeUri, $this->getElement()->type->label),
        '#id' => 'instance_type',
        '#parents' => ['instance_type'],
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => $treepath,
          ], ['query' => ['field_id' => 'instance_type']])->toString(),
          'data-field-id' => 'instance_type',
          'data-elementtype' => $treepath,
          'autocomplete' => 'off',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
    ];
    $form['instance_type']['main'] += [
      '#maxlength' => 999,
    ];
    $form['instance_serial_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ID Number'),
      '#default_value' => $this->getElement()->hasSerialNumber,
    ];
    $form['instance_acquisition_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Acquisition Date'),
      '#default_value' => $this->getElement()->hasAcquisitionDate,
    ];

    $ownerDefault = '';
    if (isset($this->getElement()->hasOwnerUri) && $this->getElement()->hasOwnerUri != NULL) {
      $ownerLabel = (string) $this->getElement()->hasOwnerUri;
      try {
        $ownerObj = $api->parseObjectResponse($api->getUri($this->getElement()->hasOwnerUri), 'getUri');
        if (is_object($ownerObj)) {
          $ownerLabel = (string) ($ownerObj->label ?? ($ownerObj->name ?? $ownerLabel));
        }
      }
      catch (\Throwable $e) {
        // Keep URI as label fallback.
      }
      $ownerDefault = Utils::fieldToAutocomplete($this->getElement()->hasOwnerUri, $ownerLabel);
    }

    $maintainerDefault = '';
    if (isset($this->getElement()->hasMaintainerUri) && $this->getElement()->hasMaintainerUri != NULL) {
      $maintainerLabel = (string) $this->getElement()->hasMaintainerUri;
      try {
        $maintainerObj = $api->parseObjectResponse($api->getUri($this->getElement()->hasMaintainerUri), 'getUri');
        if (is_object($maintainerObj)) {
          $maintainerLabel = (string) ($maintainerObj->label ?? ($maintainerObj->name ?? $maintainerLabel));
        }
      }
      catch (\Throwable $e) {
        // Keep URI as label fallback.
      }
      $maintainerDefault = Utils::fieldToAutocomplete($this->getElement()->hasMaintainerUri, $maintainerLabel);
    }

    $form['instance_owner'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Owner'),
      '#default_value' => $ownerDefault,
      '#autocomplete_route_name'       => 'rep.social_autocomplete',
      '#autocomplete_route_parameters' => [
        'entityType' => 'agent',
      ],
    ];
    $form['instance_maintainer'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maintainer'),
      '#default_value' => $maintainerDefault,
      '#autocomplete_route_name'       => 'rep.social_autocomplete',
      '#autocomplete_route_parameters' => [
        'entityType' => 'agent',
      ],
    ];
    // --- DAMAGE FIELDS INLINE ---
    // 1) Container flex/Bootstrap row
    $form['damage_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['row', 'g-3', 'mb-4'],
      ],
    ];

    // 2) isDamaged como switch
    $form['damage_wrapper']['is_damaged'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Damaged?'),
      '#title_display' => 'after',
      '#default_value' => !empty($this->getElement()->isDamaged === 'true' ? 1:0),
      '#attributes' => [
        'class' => ['form-check-input','me-2', 'ms-2'],
      ],
      '#wrapper_attributes' => [
        'class' => ['col-auto', 'form-check', 'form-switch', 'd-flex', 'align-items-center'],
        'style' => 'padding-left:0!important;margin-top:0;'
      ],
    ];

    // 3) Damage Date só visível se is_damaged == TRUE
    $form['damage_wrapper']['has_damage_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Damage Date'),
      '#default_value' => $this->getElement()->hasDamageDate ?? '',
      '#attributes' => [
        'class' => ['form-control'],
      ],
      '#wrapper_attributes' => [
        'class' => ['col-auto'],
      ],
      '#states' => [
        'visible' => [
          // dispara quando a checkbox is_damaged estiver checked
          ':input[name="is_damaged"]' => ['checked' => TRUE],
        ],
      ],
    ];
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

        $label = Utils::labelFromAutocomplete($form_state->getValue('instance_type')) . " with #ID Number (" . $form_state->getValue('instance_serial_number').")";
    // $label = Utils::labelFromAutocomplete($form_state->getValue('instance_type')) . " with ID# " . $form_state->getValue('instance_serial_number');

    try{
      $useremail = \Drupal::currentUser()->getEmail();

      $isDamaged  = $form_state->getValue('is_damaged') ? 'true' : 'false';
      $damageDate = $form_state->getValue('has_damage_date') ?: '';
      $acquisitionDate = '';
      if ($form_state->getValue('instance_acquisition_date') != NULL && $form_state->getValue('instance_acquisition_date') != '') {
        $acquisitionDate = $form_state->getValue('instance_acquisition_date');
      }

      $payload = [
        'uri'                 => $this->getElement()->uri,
        'typeUri'             => $typeUri,
        'hascoTypeUri'        => $hascoTypeUri,
        'hasStatus'           => ($isDamaged === 'true' ? VSTOI::DAMAGED : VSTOI::CURRENT),
        'label'               => $label,
        'hasSerialNumber'     => $form_state->getValue('instance_serial_number'),
        'comment'             => $form_state->getValue('instance_description'),
        'hasAcquisitionDate'  => $acquisitionDate,
        'isDamaged'           => $isDamaged,
      ];

      if ($isDamaged === 'true' && $damageDate) {
        $payload['hasDamageDate'] = $damageDate;
      }

      $payload['hasOwnerUri']      = Utils::uriFromAutocomplete($form_state->getValue('instance_owner'));
      $payload['hasMaintainerUri'] = Utils::uriFromAutocomplete($form_state->getValue('instance_maintainer'));

      $payload['hasSIRManagerEmail'] = \Drupal::currentUser()->getEmail();

      $instanceJson = json_encode($payload);

      $api = \Drupal::service('rep.api_connector');
      $apiResponse = $api->elementAdd($this->getElementType(), $instanceJson);
      $parsed = $api->parseObjectResponse($apiResponse, 'elementAdd');
      if ($parsed === NULL) {
        self::backUrl();
        return;
      }

      // Guard against responses that are already decoded but logically failed.
      $decodedResponse = NULL;
      if (is_string($apiResponse)) {
        $decodedResponse = json_decode($apiResponse);
      }
      elseif (is_array($apiResponse)) {
        $decodedResponse = (object) $apiResponse;
      }
      elseif (is_object($apiResponse)) {
        $decodedResponse = $apiResponse;
      }

      if (is_object($decodedResponse) && isset($decodedResponse->isSuccessful) && !$decodedResponse->isSuccessful) {
        $errorMessage = t('API service failed to update @name.', ['@name' => strtolower($this->getElementName())]);
        if (isset($decodedResponse->body) && is_string($decodedResponse->body) && $decodedResponse->body !== '') {
          $errorMessage = $decodedResponse->body;
        }
        \Drupal::messenger()->addError($errorMessage);
        self::backUrl();
        return;
      }

      \Drupal::messenger()->addMessage(t($this->getElementName() . " has been updated successfully."));
      self::backUrl();
      return;
    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating instance: ".$e->getMessage()));
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
