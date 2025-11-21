<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\rep\Vocabulary\REPGUI;

/**
 * Form to add a Deployment.
 *
 * When the user selects an Instrument Instance, an AJAX callback is triggered,
 * the API is called to retrieve the slots of that instrument, and a table with
 * the slot elements is rendered below the Instrument field.
 */
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
    // Get preferred instrument label from configuration.
    $preferred_instrument = \Drupal::config('rep.settings')->get('preferred_instrument') ?? 'instrument';

    // Platform instance autocomplete.
    $form['deployment_platform_instance'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Platform Instance'),
      '#autocomplete_route_name' => 'dpl.platforminstance_autocomplete',
    ];

    // Instrument instance autocomplete with AJAX.
    $form['deployment_instrument_instance'] = [
      '#type' => 'textfield',
      '#title' => $this->t(ucfirst($preferred_instrument) . ' Instance'),
      '#autocomplete_route_name' => 'dpl.instrumentinstance_autocomplete',
      '#ajax' => [
        'callback' => '::instrumentInstanceChanged',
        'event' => 'autocompleteclose', // Trigger when autocomplete selection is made.
        'wrapper' => 'instrument-slots-wrapper',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Loading instrument slots...'),
        ],
      ],
    ];

    /************************************************************
     * Slots wrapper MUST appear before "Version"
     * so that the user sees the slots table (even empty) right
     * under the Instrument Instance field.
     ************************************************************/
    $form['instrument_slots_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'instrument-slots-wrapper',
      ],
    ];

    // Check if we already have slot data from a previous AJAX callback.
    // If we do, show the table; if not, keep the wrapper empty.
    $slots_table = $form_state->get('instrument_slots');

    if (!empty($slots_table)) {
      // Use previously built slots table.
      $form['instrument_slots_wrapper']['slots_table'] = $slots_table;
    }
    // If there is no stored table yet, the wrapper will be empty.
    // This means: no table is rendered when there is no Instrument selected.

    // Deployment version (comes AFTER slots).
    $form['deployment_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#value' => 1,
      '#disabled' => TRUE,
    ];

    // Free-text description of the deployment.
    $form['deployment_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
    ];

    // Save button.
    $form['save_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#name' => 'save',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'save-button'],
      ],
    ];

    // Cancel button (goes back to the previous page).
    $form['cancel_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#name' => 'back',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'cancel-button'],
      ],
    ];

    // Simple bottom spacing.
    $form['bottom_space'] = [
      '#type' => 'item',
      '#title' => $this->t('<br><br>'),
    ];

    return $form;
  }

  /**
   * Helper: build an empty slots table with a custom empty message.
   *
   * This is used:
   *  - Initially before any instrument is selected.
   *  - As fallback when slots cannot be loaded.
   */
  protected function buildEmptySlotsTable($empty_message) {
    return [
      '#type' => 'table',
      '#header' => [
        // $this->t('Type'),
        $this->t('Label'),
        // $this->t('Priority'),
        $this->t('Element'),
      ],
      '#rows' => [],
      '#empty' => $empty_message,
    ];
  }

  /**
   * AJAX callback triggered when the Instrument Instance autocomplete closes.
   *
   * Steps:
   *  - Read the autocomplete value (label + URI).
   *  - Extract the instrument URI from it.
   *  - Load the instrument object from the API (if needed).
   *  - Call slotElements() with the correct URI (container or instrument,
   *    depending on your backend).
   *  - Build a table render array and inject it into the form wrapper.
   */
  public function instrumentInstanceChanged(array &$form, FormStateInterface $form_state) {
    // Raw value from autocomplete textfield (e.g. "Label (URI)").
    $instrument_autocomplete_value = $form_state->getValue('deployment_instrument_instance');

    // If the field is empty, we clear the table and return an empty wrapper.
    if (empty($instrument_autocomplete_value)) {
      // Remove any stored slots from form_state.
      $form_state->set('instrument_slots', NULL);

      // Ensure the wrapper has no table inside.
      if (isset($form['instrument_slots_wrapper']['slots_table'])) {
        unset($form['instrument_slots_wrapper']['slots_table']);
      }

      return $form['instrument_slots_wrapper'];
    }

    // -----------------------------------------------------------
    // From here on, we know there is some Instrument value.
    // Build the slots table as before.
    // -----------------------------------------------------------

    try {
      $instrument_uri = Utils::uriFromAutocomplete($instrument_autocomplete_value);
      $api = \Drupal::service('rep.api_connector');

      // Load instrument and resolve container URI as you already fazias:
      $instrument = $api->parseObjectResponse($api->getUri($instrument_uri), 'instrument');
      $container_uri = $instrument->typeUri; // ou o campo correto de container se mudares depois

      // Build slots table.
      $slots_render_array = self::buildSlotElements($container_uri, $api, 'table');
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError(
        $this->t('An error occurred while loading instrument slots: @message', [
          '@message' => $e->getMessage(),
        ])
      );

      // Fallback table with a clear message.
      $slots_render_array = $this->buildEmptySlotsTable(
        $this->t('No slots could be loaded for the selected Instrument Instance.')
      );
    }

    // Store and inject the table so that it is rendered in the AJAX response
    // and also available on future rebuilds.
    $form_state->set('instrument_slots', $slots_render_array);
    $form['instrument_slots_wrapper']['slots_table'] = $slots_render_array;

    return $form['instrument_slots_wrapper'];
  }

  /**
   * Validate handler.
   *
   * Currently only used as an example; you can add your own validations here.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    // Example: If you ever re-enable deployment_name, you can validate it here.
    /*
    if ($button_name != 'back') {
      if (strlen($form_state->getValue('deployment_name')) < 1) {
        $form_state->setErrorByName('deployment_name', $this->t('Please enter a valid name.'));
      }
    }
    */
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    $preferred_instrument = \Drupal::config('rep.settings')->get('preferred_instrument') ?? 'instrument';

    // If the user clicks on "Cancel", just go back.
    if ($button_name === 'back') {
      self::backUrl();
      return;
    }

    // Prepare Platform Instance URI and label.
    $platformInstanceUri = '';
    $platformInstanceName = '';
    if ($form_state->getValue('deployment_platform_instance') !== NULL && $form_state->getValue('deployment_platform_instance') !== '') {
      $platformInstanceUri = Utils::uriFromAutocomplete($form_state->getValue('deployment_platform_instance'));
      $platformInstanceName = Utils::labelFromAutocomplete($form_state->getValue('deployment_platform_instance'));
    }

    // Prepare Instrument Instance URI and label.
    $instrumentInstanceUri = '';
    $instrumentInstanceName = '';
    if ($form_state->getValue('deployment_instrument_instance') !== NULL && $form_state->getValue('deployment_instrument_instance') !== '') {
      $instrumentInstanceUri = Utils::uriFromAutocomplete($form_state->getValue('deployment_instrument_instance'));
      $instrumentInstanceName = Utils::labelFromAutocomplete($form_state->getValue('deployment_instrument_instance'));
    }

    // Build a final label for this deployment, based on what we have.
    $finalLabel = 'a deployment';
    if ($platformInstanceName == '' && $instrumentInstanceName != '') {
      $finalLabel = 'a deployment with ' . lcfirst($preferred_instrument) . ' ' . $instrumentInstanceName;
    }
    elseif ($platformInstanceName != '' && $instrumentInstanceName == '') {
      $finalLabel = 'a deployment @ ' . $platformInstanceName;
    }
    elseif ($platformInstanceName != '' && $instrumentInstanceName != '') {
      $finalLabel = $instrumentInstanceName . ' @ ' . $platformInstanceName;
    }

    // Prepare timestamp string.
    $dateTime = new \DateTime();
    $formattedNow = $dateTime->format('Y-m-d\TH:i:s') . '.' . $dateTime->format('v') . $dateTime->format('O');

    try {
      $useremail = \Drupal::currentUser()->getEmail();
      $newDeploymentUri = Utils::uriGen('deployment');

      // Build JSON payload for the API.
      $deploymentJson = '{"uri":"' . $newDeploymentUri . '",' .
        '"typeUri":"' . VSTOI::DEPLOYMENT . '",' .
        '"hascoTypeUri":"' . VSTOI::DEPLOYMENT . '",' .
        '"label":"' . $finalLabel . '",' .
        '"hasVersion":"' . ($form_state->getValue('deployment_version') ?? 1) . '",' .
        '"comment":"' . $form_state->getValue('deployment_description') . '",' .
        '"platformInstanceUri":"' . $platformInstanceUri . '",' .
        '"instrumentInstanceUri":"' . $instrumentInstanceUri . '",' .
        '"canUpdate":["' . $useremail . '"],' .
        '"designedAt":"' . $formattedNow . '",' .
        '"hasSIRManagerEmail":"' . $useremail . '"}';

      // Call the API connector to add the new deployment.
      $api = \Drupal::service('rep.api_connector');
      $api->elementAdd('deployment', $deploymentJson);

      // Success message in English.
      \Drupal::messenger()->addMessage($this->t('Deployment has been added successfully.'));

      self::backUrl();
      return;
    }
    catch (\Exception $e) {
      // Error message in English, including the exception message for debugging.
      \Drupal::messenger()->addError(
        $this->t('An error occurred while adding deployment: @message', [
          '@message' => $e->getMessage(),
        ])
      );
      self::backUrl();
      return;
    }
  }

  /**
   * Helper method to redirect back to the previous URL (if any).
   */
  protected static function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'dpl.add_deployment');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

  /*****************************************************
   * Build and render slot elements in either a table
   * or a tree format, recursively starting from
   * the instrument/container URI.
   *****************************************************/
  // public static function buildSlotElements($containerUri, $api, $renderMode = 'table') {
  //   /********************************************************
  //    * HARD GUARD:
  //    * If container URI is empty, do NOT call the API.
  //    * This avoids the backend error "Container cannot be null."
  //    * but still returns a valid empty table.
  //    ********************************************************/
  //   if (empty($containerUri)) {
  //     $header = [
  //       t('Type'),
  //       t('Label'),
  //       t('Priority'),
  //       t('Element'),
  //     ];

  //     return [
  //       '#type' => 'table',
  //       '#header' => $header,
  //       '#rows' => [],
  //       '#empty' => t('No slots available for the selected container.'),
  //     ];
  //   }

  //   // ------------------------------------------
  //   // 1) Internal recursive function:
  //   //    Build a "tree" data structure by exploring
  //   //    subcontainers, container slots, components, etc.
  //   // ------------------------------------------
  //   $buildTree = function ($uri) use (&$buildTree, $api) {
  //     $root_url = \Drupal::request()->getBaseUrl();

  //     // 1. Fetch slotElements for the current URI (instrument/container/subcontainer).
  //     $slotElements = $api->parseObjectResponse($api->slotElements($uri), 'slotElements');

  //     if (empty($slotElements)) {
  //       // No slots for this container; still a valid situation.
  //       return [];
  //     }

  //     $tree = [];

  //     // 2. Loop over each slotElement.
  //     foreach ($slotElements as $slotElement) {
  //       // Basic fields.
  //       $typeUri  = $slotElement->hascoTypeUri ?? '';
  //       $type     = Utils::namespaceUri($typeUri);
  //       $label    = $slotElement->label        ?? '';
  //       $priority = $slotElement->hasPriority  ?? '';
  //       $elemUri  = $slotElement->uri          ?? '';

  //       // Prepare a structure for the tree node.
  //       $item = [
  //         'uri'      => $elemUri,
  //         'type'     => $type,
  //         'label'    => $label,
  //         'priority' => $priority,
  //         'element'  => '',   // Will hold your custom "content" or description.
  //         'children' => [],   // Potential recursion for subcontainers or nested components.
  //       ];

  //       /****************************************************
  //        * Logic to determine if it's a subcontainer,
  //        * a container slot referencing another container,
  //        * or a leaf (component, etc.).
  //        ****************************************************/
  //       if ($typeUri === VSTOI::SUBCONTAINER) {
  //         // Mark as subcontainer.
  //         $item['element'] = 'Subcontainer: ' . ($label ?: '[no label]');

  //         // Recursively explore all slotElements inside this subcontainer.
  //         if (!empty($elemUri)) {
  //           $item['children'] = $buildTree($elemUri);
  //         }
  //       }
  //       elseif ($typeUri === VSTOI::CONTAINER_SLOT) {
  //         // Container slot may or may not have a component instance.
  //         // Container slot must still be displayed even if empty.
  //         $item['element'] = 'Slot has no component instance.'; // Message in EN.

  //         if (!empty($slotElement->hasComponent)) {
  //           // 1. Get the "component" data.
  //           $componentUri = $slotElement->hasComponent;
  //           $componentObj = $api->parseObjectResponse($api->getUri($componentUri), 'getUri');

  //           if (!empty($componentObj) && !empty($componentObj->hascoTypeUri)) {
  //             $componentType = $componentObj->hascoTypeUri;

  //             // If the component is actually a container or subcontainer,
  //             // we can recursively call buildTree on that URI.
  //             if ($componentType === VSTOI::SUBCONTAINER || $componentType === VSTOI::CONTAINER) {
  //               $item['element'] = 'ContainerSlot referencing a container: ' . ($componentObj->label ?? '[no label]');
  //               $item['children'] = $buildTree($componentObj->uri);
  //             }
  //             // If the component is a COMPONENT or other "leaf" type.
  //             elseif ($componentType === VSTOI::COMPONENT) {
  //               $typeLabel = Utils::namespaceUri($componentObj->hascoTypeUri);

  //               // Component
  //               // if (isset($componentObj->uri)) {
  //               //   $componentLink = t(
  //               //     '<b>@type</b>: [<a target="_new" href="@href">@label</a> (@status)]',
  //               //     [
  //               //       '@type' => $typeLabel,
  //               //       '@href' => $root_url . REPGUI::DESCRIBE_PAGE . base64_encode($componentObj->uri),
  //               //       '@label' => $componentObj->label,
  //               //       '@status' => Utils::plainStatus($componentObj->hasStatus),
  //               //     ]
  //               //   );
  //               // }
  //               // else {
  //               //   $componentLink = t('<b>@type</b>: [EMPTY]', ['@type' => $typeLabel]);
  //               // }

  //               // Attributof
  //               // if (isset($componentObj->isAttributeOf)) {
  //               //   $attributOfStatus = $api->parseObjectResponse($api->getUri($componentObj->isAttributeOf), 'getUri');
  //               //   $attributeOf = t(
  //               //     '<b>Attribute Of</b>: [<a target="_new" href="@href">@label</a> (@status)]',
  //               //     [
  //               //       '@href' => $root_url . REPGUI::DESCRIBE_PAGE . base64_encode(Utils::uriFromAutocomplete($componentObj->isAttributeOf)),
  //               //       '@label' => Utils::namespaceUri($componentObj->isAttributeOf),
  //               //       '@status' => (Utils::plainStatus($attributOfStatus->hasStatus) ?? 'Current'),
  //               //     ]
  //               //   );
  //               // }
  //               // else {
  //               //   $attributeOf = t('<b>Attribute Of</b>: [EMPTY]');
  //               // }

  //               // Codebook
  //               // if (isset($componentObj->codebook->label)) {
  //               //   $codebook = t(
  //               //     '<b>CB</b>: [<a target="_new" href="@href">@label</a> (@status)]',
  //               //     [
  //               //       '@href' => $root_url . REPGUI::DESCRIBE_PAGE . base64_encode($componentObj->codebook->uri),
  //               //       '@label' => $componentObj->codebook->label,
  //               //       '@status' => Utils::plainStatus($componentObj->codebook->hasStatus),
  //               //     ]
  //               //   );
  //               // }
  //               // else {
  //               //   $codebook = t('<b>CB</b>: [EMPTY]');
  //               // }

  //               // Final element description for a slot that has a component instance.
  //               // $item['element'] = $componentLink . ' ' . $attributeOf . ' ' . $codebook;
  //               $item['element'] = $componentObj->uri;
  //             }
  //             else {
  //               // Unknown or other type.
  //               $item['element'] = 'ContainerSlot referencing: ' . Utils::namespaceUri($componentType);
  //             }
  //           }
  //         }
  //       }
  //       else {
  //         // Unknown or other type.
  //         $item['element'] = '(Unknown type: ' . $type . ')';
  //       }

  //       // Add this item to the tree array.
  //       $tree[] = $item;
  //     }

  //     return $tree;
  //   };

  //   // ------------------------------------------
  //   // 2) Render the tree data as nested tables.
  //   //    (tree mode is kept just in case, but you
  //   //     are using 'table' for this form.)
  //   // ------------------------------------------
  //   $renderAsTable = function (array $tree) use (&$renderAsTable) {
  //     // Define the table header.
  //     $header = [
  //       // t('Type'),
  //       t('Label'),
  //       // t('Priority'),
  //       t('Element'),
  //     ];

  //     $rows = [];
  //     foreach ($tree as $item) {
  //       // Check if the current item is a subcontainer.
  //       if ($item['type'] === Utils::namespaceUri(VSTOI::SUBCONTAINER)) {
  //         // Insert a row that says "Sub-container: X".
  //         $rows[] = [
  //           [
  //             'data' => t('Sub-container: <strong>@label</strong>', ['@label' => $item['label']]),
  //             'colspan' => 4,  // Spans all columns.
  //             'class' => ['subcontainer-title'], // Optional CSS class.
  //           ],
  //         ];
  //       }
  //       else {
  //         // Normal item (container slot, component, etc.).
  //         $rows[] = [
  //           // $item['type'],
  //           $item['label'],
  //           // $item['priority'],
  //           ['data' => $item['element'], 'escape' => FALSE],
  //         ];
  //       }

  //       // If there are children, render them as a sub-table.
  //       if (!empty($item['children'])) {
  //         $subTable = $renderAsTable($item['children']);
  //         $rows[] = [
  //           [
  //             'data' => $subTable,
  //             'colspan' => 4,
  //           ],
  //         ];
  //       }
  //       else {
  //         // If there are no child elements for a subcontainer, show a message.
  //         if (empty($item['children']) && $item['type'] === Utils::namespaceUri(VSTOI::SUBCONTAINER)) {
  //           $rows[] = [
  //             [
  //               'data' => t('<span style="padding-left:50px;"><em>Sub-container has no elements.</em></span>'),
  //               'colspan' => 4,
  //               'escape' => FALSE,
  //             ],
  //           ];
  //         }
  //       }
  //     }

  //     // Return a Drupal render array for the table.
  //     return [
  //       '#type' => 'table',
  //       '#header' => $header,
  //       '#rows' => $rows,
  //       '#empty' => t('No response options found'),
  //     ];
  //   };
  // }

    /*****************************************************
   * Build and render slot elements in either a table
   * or a tree format, recursively starting from
   * the instrument/container URI.
   *****************************************************/
  public static function buildSlotElements($containerUri, $api, $renderMode = 'table') {
    /********************************************************
     * HARD GUARD:
     * If container URI is empty, do NOT call the API.
     * This avoids the backend error "Container cannot be null."
     * but still returns a valid empty table.
     ********************************************************/
    if (empty($containerUri)) {
      $header = [
        t('Label'),
        t('Element'),
      ];

      return [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => [],
        '#empty' => t('No slots available for the selected container.'),
      ];
    }

    // ------------------------------------------
    // 1) Internal recursive function:
    //    Build a "tree" data structure by exploring
    //    subcontainers, container slots, components, etc.
    // ------------------------------------------
    $buildTree = function ($uri) use (&$buildTree, $api) {
      $root_url = \Drupal::request()->getBaseUrl();

      // 1. Fetch slotElements for the current URI (instrument/container/subcontainer).
      $slotElements = $api->parseObjectResponse($api->slotElements($uri), 'slotElements');

      if (empty($slotElements)) {
        // No slots for this container; still a valid situation.
        return [];
      }

      $tree = [];

      // 2. Loop over each slotElement.
      foreach ($slotElements as $slotElement) {
        // Basic fields.
        $typeUri  = $slotElement->hascoTypeUri ?? '';
        $type     = Utils::namespaceUri($typeUri);
        $label    = $slotElement->label        ?? '';
        $priority = $slotElement->hasPriority  ?? '';
        $elemUri  = $slotElement->uri          ?? '';

        // Prepare a structure for the tree node.
        $item = [
          'uri'      => $elemUri,
          'type'     => $type,
          'label'    => $label,
          'priority' => $priority,
          'element'  => '',   // Will hold your custom "content" or description.
          'children' => [],   // Potential recursion for subcontainers or nested components.
        ];

        /****************************************************
         * Logic to determine if it's a subcontainer,
         * a container slot referencing another container,
         * or a leaf (component, etc.).
         ****************************************************/
        if ($typeUri === VSTOI::SUBCONTAINER) {
          // Mark as subcontainer.
          $item['element'] = 'Subcontainer: ' . ($label ?: '[no label]');

          // Recursively explore all slotElements inside this subcontainer.
          if (!empty($elemUri)) {
            $item['children'] = $buildTree($elemUri);
          }
        }
        elseif ($typeUri === VSTOI::CONTAINER_SLOT) {
          // Container slot may or may not have a component instance.
          // Container slot must still be displayed even if empty.
          $item['element'] = 'Slot has no component instance.'; // Message in EN.

          if (!empty($slotElement->hasComponent)) {
            // 1. Get the "component" data.
            $componentUri = $slotElement->hasComponent;
            $componentObj = $api->parseObjectResponse($api->getUri($componentUri), 'getUri');

            if (!empty($componentObj) && !empty($componentObj->hascoTypeUri)) {
              $componentType = $componentObj->hascoTypeUri;

              // If the component is actually a container or subcontainer,
              // we can recursively call buildTree on that URI.
              if ($componentType === VSTOI::SUBCONTAINER || $componentType === VSTOI::CONTAINER) {
                $item['element'] = 'ContainerSlot referencing a container: ' . ($componentObj->label ?? '[no label]');
                $item['children'] = $buildTree($componentObj->uri);
              }
              // If the component is a COMPONENT or other "leaf" type.
              elseif ($componentType === VSTOI::COMPONENT) {
                // For now we only show the component URI or a simple string.
                // You can plug back the rich HTML version later if you want.
                $item['element'] = $componentObj->uri ?? 'Component instance';
              }
              else {
                // Unknown or other type.
                $item['element'] = 'ContainerSlot referencing: ' . Utils::namespaceUri($componentType);
              }
            }
          }
        }
        else {
          // Unknown or other type.
          $item['element'] = '(Unknown type: ' . $type . ')';
        }

        // Add this item to the tree array.
        $tree[] = $item;
      }

      return $tree;
    };

    // ------------------------------------------
    // 2) Render the tree data as nested tables.
    // ------------------------------------------
    // $renderAsTable = function (array $tree) use (&$renderAsTable) {
    //   // Define the table header (Label + Element only).
    //   $header = [
    //     t('Label'),
    //     t('Element'),
    //   ];

    //   $rows = [];
    //   foreach ($tree as $item) {
    //     // Check if the current item is a subcontainer.
    //     if ($item['type'] === Utils::namespaceUri(VSTOI::SUBCONTAINER)) {
    //       // Insert a row that says "Sub-container: X".
    //       $rows[] = [
    //         [
    //           'data' => t('Sub-container: <strong>@label</strong>', ['@label' => $item['label']]),
    //           'colspan' => 2,  // We now have only 2 columns (Label, Element).
    //           'class' => ['subcontainer-title'],
    //           'escape' => FALSE,
    //         ],
    //       ];
    //     }
    //     else {
    //       // Normal item (container slot, component, etc.).
    //       $rows[] = [
    //         $item['label'],
    //         ['data' => $item['element'], 'escape' => FALSE],
    //       ];
    //     }

    //     // If there are children, render them as a sub-table.
    //     if (!empty($item['children'])) {
    //       $subTable = $renderAsTable($item['children']);
    //       $rows[] = [
    //         [
    //           'data' => $subTable,
    //           'colspan' => 2,
    //         ],
    //       ];
    //     }
    //     else {
    //       // If there are no child elements for a subcontainer, show a message.
    //       if (empty($item['children']) && $item['type'] === Utils::namespaceUri(VSTOI::SUBCONTAINER)) {
    //         $rows[] = [
    //           [
    //             'data' => t('<span style="padding-left:50px;"><em>Sub-container has no elements.</em></span>'),
    //             'colspan' => 2,
    //             'escape' => FALSE,
    //           ],
    //         ];
    //       }
    //     }
    //   }

    //   // Return a Drupal render array for the table.
    //   return [
    //     '#type' => 'table',
    //     '#header' => $header,
    //     '#rows' => $rows,
    //     '#empty' => t('No response options found'),
    //   ];
    // };
        // ------------------------------------------
    // 2) Render the tree data as nested tables.
    // ------------------------------------------
    $renderAsTable = function (array $tree) use (&$renderAsTable) {
      // Define the table header (Label + Element).
      $header = [
        t('Label'),
        t('Element'),
      ];

      $rows = [];
      foreach ($tree as $item) {
        $is_subcontainer = ($item['type'] === Utils::namespaceUri(VSTOI::SUBCONTAINER));

        if ($is_subcontainer) {
          // First row: sub-container title, with its own row class.
          $rows[] = [
            'class' => ['subcontainer-block', 'subcontainer-title-row'],
            'data' => [
              [
                'data' => t('Sub-container: <strong>@label</strong>', ['@label' => $item['label']]),
                'colspan' => 2, // We have 2 columns: Label + Element.
                'class' => ['subcontainer-title'],
                'escape' => FALSE,
              ],
            ],
          ];
        }
        else {
          // Normal item (container slot, component, etc.).
          $rows[] = [
            'data' => [
              $item['label'],
              ['data' => $item['element'], 'escape' => FALSE],
            ],
          ];
        }

        // If there are children, render them as a sub-table.
        if (!empty($item['children'])) {
          $subTable = $renderAsTable($item['children']);

          $rows[] = [
            'class' => $is_subcontainer
              ? ['subcontainer-block', 'subcontainer-children-row']
              : [],
            'data' => [
              [
                'data' => $subTable,
                'colspan' => 2,
              ],
            ],
          ];
        }
        else {
          // If there are no child elements for a subcontainer, show a message.
          if ($is_subcontainer) {
            $rows[] = [
              'class' => ['subcontainer-block', 'subcontainer-empty-row'],
              'data' => [
                [
                  'data' => t('<span style="padding-left:50px;"><em>Sub-container has no elements.</em></span>'),
                  'colspan' => 2,
                  'escape' => FALSE,
                ],
              ],
            ];
          }
        }
      }

      // Return a Drupal render array for the table.
      return [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => t('No response options found'),
        '#attributes' => [
          // Optional wrapper class so you can target this specific table.
          'class' => ['instrument-slots-table'],
        ],
      ];
    };

    // ------------------------------------------
    // 3) Build the tree data from the top-level
    //    container/instrument URI, then render it.
    // ------------------------------------------
    $tree = $buildTree($containerUri);

    // In this form we always use "table" mode, but we keep the parameter
    // in case you want to support other modes in the future.
    return $renderAsTable($tree);
  }


}
