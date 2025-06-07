<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\REPGUI;

/**
 * Form for reviewing a Deployment, displaying separate tabs for
 * Platform Instance, Platform Elements, Instrument Instance, and Instrument Elements.
 */
class ViewDeploymentForm extends FormBase {

  protected $deploymentUri;
  protected $deployment;
  protected $instrumentInstance;
  protected $platformInstance;
  protected $instrument;
  protected $container;
  protected $platform;


  public function getFormId() {
    return 'view_deployment_form';
  }

  public function setDeploymentUri($uri) {
    $this->deploymentUri = $uri;
  }

  public function getDeployment() {
    return $this->deployment;
  }

  public function setInstrumentInstance($instrumentInstance) {
    $this->instrumentInstance = $instrumentInstance;
  }

  public function getInstrumentInstance() {
    return $this->instrumentInstance;
  }

  public function setPlatformInstance($platformInstance) {
    $this->platformInstance = $platformInstance;
  }

  public function getPlatformInstance() {
    return $this->platformInstance;
  }

  public function setInstrument($instrument) {
    $this->instrument = $instrument;
  }

  public function getPlatform() {
    return $this->platform;
  }

  public function setPlatform($platform) {
    $this->platform = $platform;
  }

  public function getInstrument() {
    return $this->instrument;
  }

  public function setContainer($container) {
    $this->container = $container;
  }

  public function getContainer() {
    return $this->container;
  }

  /**
   * {@inheritdoc}
   *
   * Builds the form, retrieving the Deployment JSON via API and splitting
   * its sub‐objects into four tabs:
   *   - Platform Instance
   *   - Platform Elements
   *   - Instrument Instance
   *   - Instrument Elements
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param string|null $deploymenturi
   *   The Base64‐encoded URI of the deployment.
   *
   * @return array
   *   The rendered form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $deploymenturi = NULL) {

    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();

    $socialEnabled = \Drupal::config('rep.settings')->get('social_conf');

    // Attach libraries for modal/dialog if needed.
    $form['#attached']['library'][] = 'core/drupal.dialog';
    $form['#attached']['library'][] = 'rep/rep_modal';

    // Decode the Base64 string and store internally.
    $uri_decode = base64_decode($deploymenturi);
    $this->setDeploymentUri($uri_decode);

    // Call the API to retrieve the deployment object.
    $api = \Drupal::service('rep.api_connector');
    $raw_response = $api->getUri($this->deploymentUri);
    $result = json_decode($raw_response);

    // If the API call failed or returned an error flag, show error and go back.
    if (empty($result->isSuccessful) || !$result->isSuccessful) {
      \Drupal::messenger()->addError($this->t('Failed to retrieve Deployment.'));
      return;
    }

    // The API returns an array in "body"; take the first (and only) element.
    $this->deployment = $result->body;

    if (isset($this->getDeployment()->instrumentInstanceUri) && $this->getDeployment()->instrumentInstanceUri !== null) {
      $instrumentInstance_response = $api->getUri($this->getDeployment()->instrumentInstanceUri);
      $resultInstrumentInstance = json_decode($instrumentInstance_response);
      $this->instrumentInstance = $resultInstrumentInstance->body;

      $instrument_response = $api->getUri($this->getInstrumentInstance()->typeUri);
      $resultInstrument = json_decode($instrument_response);
      $this->instrument = $resultInstrument->body;
    }

    if (isset($this->getDeployment()->platformInstanceUri) && $this->getDeployment()->platformInstanceUri !== null) {
      $platformInstance_response = $api->getUri($this->getDeployment()->platformInstanceUri);
      $resultPlatformInstance = json_decode($platformInstance_response);
      $this->platformInstance = $resultPlatformInstance->body;
    }

    // Wrap the form in a container div (optional).
    $form['#prefix'] = '<div class="review-deployment-form">';
    $form['#suffix'] = '</div>';

    // Create the vertical tabs container.
    $form['tabs'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-platform-instance',
      '#weight' => -10,
    ];

    //
    // TAB: Platform Instance
    //
    $form['platform_instance'] = [
      '#type' => 'details',
      '#title' => $this->t('Platform Instance'),
      '#group' => 'tabs',
    ];

    $platform = $this->deployment->platformInstance;
    if (empty($platform)) {
      // If no platformInstance was provided, show a warning message.
      $form['platform_instance']['no_platform'] = [
        '#type' => 'item',
        '#markup' => '<p class="text-warning">' . $this->t('This Deployment has no associated Platform Instance.') . '</p>',
      ];
    }
    else {
      // Display each relevant field as a disabled item or textfield.

      // URI (show as plain text item).
      $form['platform_instance']['platform_uri'] = [
        '#type' => 'item',
        '#title' => $this->t('URI'),
        '#markup' => t('<a target="_new" href="' . $root_url . REPGUI::DESCRIBE_PAGE . base64_encode($platform->uri) . '">' . $platform->uri . '</a>'),
        '#wrapper_attributes' => [
          'class' => ['mt-3']
        ],
      ];

      // Label (disabled textfield for viewing only).
      $form['platform_instance']['platform_label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#default_value' => $platform->label,
        '#disabled' => TRUE,
      ];

      // Type URI (disabled textfield).
      $form['platform_instance']['platform_type'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Type URI'),
        '#default_value' => UTILS::fieldToAutocomplete($platform->typeUri, $platform->typeLabel),
        '#disabled' => TRUE,
      ];

      // Status (disabled textfield), may be empty if not set.
      $form['platform_instance']['platform_status'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Status'),
        '#default_value' => UTILS::plainStatus($platform->hasStatus) ?? '',
        '#disabled' => TRUE,
      ];

      // Serial Number (disabled textfield).
      $form['platform_instance']['platform_serial_number'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Serial Number'),
        '#default_value' => $platform->hasSerialNumber ?? '',
        '#disabled' => TRUE,
      ];

      // Acquisition Date (disabled textfield).
      $form['platform_instance']['platform_acquisition_date'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Acquisition Date'),
        '#default_value' => $platform->hasAcquisitionDate ?? '',
        '#disabled' => TRUE,
      ];

      if ($socialEnabled) {
      // Owner (disabled textfield).
        $form['platform_instance']['platform_owner'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Owner'),
          '#default_value' => $platform->hasOwner ?? '',
          '#disabled' => TRUE,
        ];

        // Maintainer (disabled textfield).
        $form['platform_instance']['platform_maintainer'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Maintainer'),
          '#default_value' => $platform->hasMaintainer ?? '',
          '#disabled' => TRUE,
        ];
      }

      // SIR Manager Email (disabled textfield).
      $form['platform_instance']['platform_manager'] = [
        '#type' => 'textfield',
        '#title' => $this->t('SIR Manager Email'),
        '#default_value' => $platform->hasSIRManagerEmail ?? '',
        '#disabled' => TRUE,
      ];
    }

    //
    // TAB: Platform Elements
    //
    $form['platform_elements'] = [
      '#type' => 'details',
      '#title' => $this->t('Platform Elements'),
      '#group' => 'tabs',
    ];

    if (empty($platform)) {
      // If no platformInstance, we cannot fetch elements.
      $form['platform_elements']['no_platform_elements'] = [
        '#type' => 'item',
        '#markup' => '<p class="text-warning">' . $this->t('No Platform Instance available to show elements.') . '</p>',
      ];
    }
    else {
      // 1) From platformInstance, get the platform type URI.
      $platformTypeUri = $platform->typeUri;

      if (empty($platformTypeUri)) {
        $form['platform_elements']['no_elements'] = [
          '#type' => 'item',
          '#markup' => '<p class="text-warning">' . $this->t('Platform Type URI is missing.') . '</p>',
        ];
      }
      else {
        // 2) Call API to retrieve the platform type object.
        $raw_platform_type = $api->getUri($platformTypeUri);
        $platform_type_result = json_decode($raw_platform_type);

        if (empty($platform_type_result->isSuccessful) || !$platform_type_result->isSuccessful) {
          $form['platform_elements']['api_error'] = [
            '#type' => 'item',
            '#markup' => '<p class="text-warning">' . $this->t('Failed to retrieve Platform Type.') . '</p>',
          ];
        }
        else {
          // The API returns an array in 'body'; take the first element.
          $platformTypeObj = $platform_type_result->body;

          // 3) Determine the first container URI (hasFirst) from the platform type.
          $firstContainerUri = $platformTypeObj->hasFirst ?? '';

          if (empty($firstContainerUri)) {
            $form['platform_elements']['no_structure'] = [
              '#type' => 'item',
              '#markup' => '<p class="text-warning">' . $this->t('This Platform Type has no defined structure.') . '</p>',
            ];
          }
          else {
            // 4) Fetch the container object for the first slot.
            $containerResponse = $api->parseObjectResponse($api->getUri($firstContainerUri), 'getUri');
            if (empty($containerResponse)) {
              $form['platform_elements']['container_error'] = [
                '#type' => 'item',
                '#markup' => '<p class="text-warning">' . $this->t('Failed to retrieve Platform container structure.') . '</p>',
              ];
            }
            else {
              // 5) Build a nested table of slot elements for this container.
              $containerUri = $containerResponse->uri;
              $slotElementsTable = Utils::buildSlotElements($containerUri, $api, 'table');

              // 6) Render the slot elements table in the form.
              $form['platform_elements']['slot_elements'] = $slotElementsTable;
            }
          }
        }
      }
    }

    //
    // TAB: Instrument Instance
    //
    $form['instrument_instance'] = [
      '#type' => 'details',
      '#title' => $this->t('Instrument Instance'),
      '#group' => 'tabs',
    ];

    $instrument = $this->deployment->instrumentInstance;
    if (empty($instrument)) {
      // If no instrumentInstance was provided, show a warning message.
      $form['instrument_instance']['no_instrument'] = [
        '#type' => 'item',
        '#markup' => '<p class="text-warning">' . $this->t('This Deployment has no associated Instrument Instance.') . '</p>',
      ];
    }
    else {
      // Display each relevant field as a disabled item or textfield.

      // URI (show as plain text item).
      $form['instrument_instance']['instrument_uri'] = [
        '#type' => 'item',
        '#title' => $this->t('URI'),
        '#markup' => t('<a target="_new" href="' . $root_url . REPGUI::DESCRIBE_PAGE . base64_encode($instrument->uri) . '">' . $instrument->uri . '</a>'),
        '#wrapper_attributes' => [
          'class' => ['mt-3']
        ],
      ];

      // Label (disabled textfield).
      $form['instrument_instance']['instrument_label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#default_value' => $instrument->label,
        '#disabled' => TRUE,
      ];

      // Type URI (disabled textfield).
      $form['instrument_instance']['instrument_type'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Type URI'),
        '#default_value' => UTILS::fieldToAutocomplete($instrument->typeUri, $instrument->typeLabel),
        '#disabled' => TRUE,
      ];

      // Status (disabled textfield), may be empty if not set.
      $form['instrument_instance']['instrument_status'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Status'),
        '#default_value' => UTILS::plainStatus($instrument->hasStatus) ?? '',
        '#disabled' => TRUE,
      ];

      // Serial Number (disabled textfield).
      $form['instrument_instance']['instrument_serial_number'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Serial Number'),
        '#default_value' => $instrument->hasSerialNumber ?? '',
        '#disabled' => TRUE,
      ];

      // Acquisition Date (disabled textfield).
      $form['instrument_instance']['instrument_acquisition_date'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Acquisition Date'),
        '#default_value' => $instrument->hasAcquisitionDate ?? '',
        '#disabled' => TRUE,
      ];

      if ($socialEnabled) {
        // Owner (disabled textfield).
        $form['instrument_instance']['instrument_owner'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Owner'),
          '#default_value' => $instrument->hasOwner ?? '',
          '#disabled' => TRUE,
        ];

        // Maintainer (disabled textfield).
        $form['instrument_instance']['instrument_maintainer'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Maintainer'),
          '#default_value' => $instrument->hasMaintainer ?? '',
          '#disabled' => TRUE,
        ];
      }

      // SIR Manager Email (disabled textfield).
      $form['instrument_instance']['instrument_manager'] = [
        '#type' => 'textfield',
        '#title' => $this->t('SIR Manager Email'),
        '#default_value' => $instrument->hasSIRManagerEmail ?? '',
        '#disabled' => TRUE,
      ];
    }

    //
    // TAB: Instrument Elements
    //
    $form['instrument_elements'] = [
      '#type' => 'details',
      '#title' => $this->t('Instrument Container'),
      '#group' => 'tabs',
    ];

    if (empty($instrument)) {
      // If no instrumentInstance, we cannot fetch elements.
      $form['instrument_elements']['no_instrument_elements'] = [
        '#type' => 'item',
        '#markup' => '<p class="text-warning">' . $this->t('No Instrument Instance available to show elements.') . '</p>',
      ];
    }
    else {
      // **************
      // CONTAINER AREA
      // **************

      # POPULATE DATA
      $uri=$this->getInstrumentInstance()->typeUri;
      $api = \Drupal::service('rep.api_connector');
      $container = $api->parseObjectResponse($api->getUri($uri),'getUri');
      if ($container == NULL) {

        // Give message to the user saying that there is no structure for current Simulator
        $form['instrument_elements']['instrument_structure']['no_structure_warning'] = [
          '#type' => 'item',
          '#value' => t('This Simulator has no Structure bellow!')
        ];

        return;
      }

      $form['instrument_elements']['instrument_structure']['scope'] = [
        '#type' => 'item',
        '#title' => t('<h4>Slots Elements of Container <font color="DarkGreen">' . $this->getInstrument()->label . '</font>, maintained by <font color="DarkGreen">' . $this->getInstrument()->hasSIRManagerEmail . '</font></h4>'),
        '#wrapper_attributes' => [
          'class' => 'mt-3'
        ],
      ];

      $this->setContainer($container);
      $containerUri = $this->getContainer()->uri;
      $slotElementsOutput = UTILS::buildSlotElements($containerUri, $api, 'table'); // or 'tree'
      $form['instrument_elements']['instrument_structure']['slot_elements'] = $slotElementsOutput;
    }

    // Actions container at the bottom with a single "Back" button.
    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['mt-3']],
    ];
    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#button_type' => 'secondary',
      '#submit' => ['::backButtonSubmit'],
      '#attributes' => [
        'class' => ['btn', 'btn-secondary', 'back-button', 'mb-5'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * No custom validation is needed since all fields are read‐only.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Nothing to validate.
  }

  /**
   * {@inheritdoc}
   *
   * Only handles the "Back" button to return to the previous page.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * “Back” button submit handler: limpa caches e redireciona ao referrer.
   */
  public function backButtonSubmit(array &$form, FormStateInterface $form_state) {
    \Drupal::state()->delete('my_form_basic');
    \Drupal::state()->delete('my_form_variables');
    \Drupal::state()->delete('my_form_objects');
    \Drupal::state()->delete('my_form_codes');

    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'std.manage_study_elements');
    if (!$previousUrl) {
      return;
    }

    if (strpos($previousUrl, '/std/stream-data-ajax') !== FALSE) {
      $parts = parse_url($previousUrl);
      if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
        if (!empty($query['studyUri'])) {
          $form_state->setRedirect(
            'std.manage_study_elements',
            ['studyuri' => $query['studyUri']]
          );
          return;
        }
      }
    }
    else {
      $form_state->setRedirectUrl(Url::fromUserInput($previousUrl));
      return;
    }
  }
}
