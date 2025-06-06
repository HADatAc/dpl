<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\HASCO;

/**
 * Form EditStreamForm.
 *
 * Provides a form to edit an existing Stream entity with two dynamic tabs:
 * - File-Method Properties (only when method = 'files')
 * - Message-Method Properties (only when method = 'messages')
 *
 * Uses server-side #access to include or exclude tabs based on the chosen method,
 * and AJAX to rebuild tabs when the method changes.
 */
class EditStreamForm extends FormBase {

  /**
   * The Stream object loaded from API.
   *
   * @var object
   */
  protected $stream;

  /**
   * Getter for the Stream.
   *
   * @return object
   */
  public function getStream() {
    return $this->stream;
  }

  /**
   * Setter for the Stream.
   *
   * @param object $stream
   *   Stream data returned from the API.
   */
  public function setStream($stream) {
    $this->stream = $stream;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_stream_form';
  }

  /**
   * {@inheritdoc}
   *
   * Build the edit form with three tabs:
   * - Basic Properties: always visible
   * - File-Method Properties: visible when method = 'files'
   * - Message-Method Properties: visible when method = 'messages'
   *
   * Tabs are rendered conditionally via #access, and rebuilt via AJAX on method change.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $streamuri = NULL) {
    // Attach the custom tabs library and Drupal States for AJAX rebuild.
    $form['#attached']['library'][] = 'dpl/dpl_onlytabs';
    $form['#attached']['library'][] = 'core/drupal.states';
    $form['#attached']['library'][] = 'core/jquery.once';

    // 1) Load the existing Stream from the API.
    $api = \Drupal::service('rep.api_connector');
    $decoded = base64_decode($streamuri);
    $response = json_decode($api->getUri($decoded));
    if (empty($response->isSuccessful)) {
      \Drupal::messenger()->addError($this->t('Failed to retrieve Stream.'));
      $this->backUrl();
      return [];
    }
    $this->setStream($response->body);

    // dpm($this->getStream());

    // 2) Prepare the deployment autocomplete label.
    $deploymentLabel = '';
    if (!empty($this->stream->deployment) && isset($this->stream->deployment->uri, $this->stream->deployment->label)) {
      $deploymentLabel = Utils::fieldToAutocomplete(
        $this->stream->deployment->uri,
        $this->stream->deployment->label
      );
    }

    // 3) Determine the current method, falling back to the Stream's value.
    $method = $form_state->getValue('stream_method', $this->stream->method);

    // 4) AJAX container wrapping all tabs.
    $form['tabs'] = [
      '#type' => 'container',
      '#prefix' => '<div id="method-properties-wrapper">',
      '#suffix' => '</div>',
      '#attributes' => ['class' => ['tabs']],
    ];

    // 5) Build the tab navigation links.
    $form['tabs']['tab_links'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['nav', 'nav-tabs']],
    ];

    // --- Tab 1: Basic Properties link (always rendered) ---
    $form['tabs']['tab_links']['basic'] = [
      '#type' => 'html_tag',
      '#tag' => 'li',
      '#attributes' => ['class' => ['nav-item']],
      '#value' => '<a class="nav-link active" data-toggle="tab" href="#edit-tab1">'
        . $this->t('Basic Properties') . '</a>',
    ];

    // --- Tab 2: File-Method Properties link (only when files) ---
    $form['tabs']['tab_links']['file'] = [
      '#type' => 'html_tag',
      '#tag' => 'li',
      '#access' => ($method === 'files'),
      '#attributes' => ['class' => ['nav-item']],
      '#value' => '<a class="nav-link" data-toggle="tab" href="#edit-tab2">'
        . $this->t('File-Method Properties') . '</a>',
    ];

    // --- Tab 3: Message-Method Properties link (only when messages) ---
    $form['tabs']['tab_links']['message'] = [
      '#type' => 'html_tag',
      '#tag' => 'li',
      '#access' => ($method === 'messages'),
      '#attributes' => ['class' => ['nav-item']],
      '#value' => '<a class="nav-link" data-toggle="tab" href="#edit-tab3">'
        . $this->t('Message-Method Properties') . '</a>',
    ];

    // 6) Build the tab content container.
    $form['tabs']['tab_content'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tab-content']],
    ];

    //
    // === TAB 1 CONTENT: Basic Properties ===
    //
    $form['tabs']['tab_content']['tab1'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['tab-pane', 'active', 'p-3', 'border', 'border-light'],
        'id' => 'edit-tab1',
      ],
    ];
    // Deployment field (disabled autocomplete).
    $form['tabs']['tab_content']['tab1']['stream_deployment'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Deployment'),
      '#default_value' => $deploymentLabel,
      '#disabled' => TRUE,
      '#required' => TRUE,
    ];
    // Method selector with AJAX callback.
    $form['tabs']['tab_content']['tab1']['stream_method'] = [
      '#type' => 'select',
      '#title' => $this->t('Method'),
      '#options' => [
        'files' => $this->t('Files'),
        'messages' => $this->t('Messages'),
      ],
      '#default_value' => $method,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateMethodProperties',
        'event' => 'change',
        'wrapper' => 'method-properties-wrapper',
      ],
    ];
    // Permission select.
    $form['tabs']['tab_content']['tab1']['permission_uri'] = [
      '#type' => 'select',
      '#title' => $this->t('Permission'),
      '#options' => [
        HASCO::PUBLIC  => $this->t('Public'),
        HASCO::PRIVATE => $this->t('Private'),
      ],
      '#default_value' => $this->getStream()->permissionUri,
      '#required' => TRUE,
    ];
    // Study autocomplete.
    $form['tabs']['tab_content']['tab1']['stream_study'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Study'),
      '#autocomplete_route_name' => 'std.study_autocomplete',
      '#default_value' => Utils::fieldToAutocomplete($this->getStream()->studyUri, $this->getStream()->study->label),
    ];
    // SDD autocomplete.
    $form['tabs']['tab_content']['tab1']['stream_semanticdatadictionary'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SDD'),
      '#autocomplete_route_name' => 'std.semanticdatadictionary_autocomplete',
      '#default_value' => Utils::fieldToAutocomplete($this->getStream()->semanticDataDictionaryUri, $this->getStream()->semanticDataDictionary->label),
    ];
    // Version (read-only).
    $form['tabs']['tab_content']['tab1']['stream_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $this->stream->hasVersion,
      '#disabled' => TRUE,
    ];
    // Description.
    $form['tabs']['tab_content']['tab1']['stream_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->stream->comment,
    ];

    //
    // === TAB 2 CONTENT: File-Method Properties ===
    //
    $form['tabs']['tab_content']['tab2'] = [
      '#type' => 'container',
      '#access' => ($method === 'files'),
      '#attributes' => [
        'class' => ['tab-pane', 'p-3', 'border', 'border-light'],
        'id' => 'edit-tab2',
      ],
    ];
    // Datafile Pattern.
    $form['tabs']['tab_content']['tab2']['stream_datafile_pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Datafile Pattern'),
      '#default_value' => $this->stream->datafilePattern ?? '',
      '#required' => ($method === 'files'),
    ];
    // Cell Scope URI.
    $form['tabs']['tab_content']['tab2']['stream_cell_scope_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cell Scope URI'),
      '#default_value' => $this->stream->cellScopeUri ?? '',
      // '#required' => ($method === 'files'),
    ];
    // Cell Scope Name.
    $form['tabs']['tab_content']['tab2']['stream_cell_scope_name'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Cell Scope Name'),
      '#default_value' => $this->stream->cellScopeName ?? '',
      // '#required' => ($method === 'files'),
    ];

    //
    // === TAB 3 CONTENT: Message-Method Properties ===
    //
    $form['tabs']['tab_content']['tab3'] = [
      '#type' => 'container',
      '#access' => ($method === 'messages'),
      '#attributes' => [
        'class' => ['tab-pane', 'p-3', 'border', 'border-light'],
        'id' => 'edit-tab3',
      ],
    ];
    // Protocol.
    $form['tabs']['tab_content']['tab3']['stream_protocol'] = [
      '#type' => 'select',
      '#title' => $this->t('Protocol'),
      '#options' => ['MQTT' => 'MQTT', 'HTML' => 'HTML', 'ROS' => 'ROS'],
      '#default_value' => $this->stream->messageProtocol,
      '#required' => ($method === 'messages'),
    ];
    // IP, Port, Header, Archive ID.
    foreach ([
      'stream_ip' => 'messageIP',
      'stream_port' => 'messagePort',
      // 'stream_header' => 'messageHeader',
      'stream_archive_id' => 'messageArchiveId',
    ] as $field => $property) {
      $form['tabs']['tab_content']['tab3'][$field] = [
        '#type' => 'textfield',
        '#title' => $this->t(ucfirst(str_replace('message', '', $property))),
        '#default_value' => $this->stream->{$property} ?? '',
        '#required' => ($method === 'messages'),
      ];
    }

    // Header
    $form['tabs']['tab_content']['tab3']['stream_header'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Header'),
      '#default_value' => '',
      // '#required' => ($method === 'messages'),
    ];

    // 7) Action buttons: Save and Cancel.
    $form['save_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#attributes' => ['class' => ['btn', 'btn-primary', 'save-button']],
    ];
    $form['cancel_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#name' => 'back',
      '#submit' => ['::backUrl'],
      '#limit_validation_errors' => [],
      '#attributes' => ['class' => ['btn', 'btn-danger', 'cancel-button']],
    ];

    $form['space_0'] = [
      '#type' => 'item',
      '#markup' => '<br><br>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Placeholder for validation logic if needed.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Add any custom validation here.
  }

  /**
   * {@inheritdoc}
   *
   * Handles submit: updates via API or redirects on cancel.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement()['#name'];
    if ($trigger === 'back') {
      $this->backUrl();
      return;
    }

    // Build JSON payload for update.
    $email = \Drupal::currentUser()->getEmail();
    $payload = [
      'uri'                       => $this->stream->uri,
      'typeUri'                   => HASCO::STREAM,
      'hascoTypeUri'              => HASCO::STREAM,
      'label'                     => 'Stream',
      'method'                    => $form_state->getValue('stream_method'),
      'deploymentUri'             => $this->stream->deploymentUri,
      'studyUri'                  => Utils::uriFromAutocomplete($form_state->getValue('stream_study')),
      'semanticDataDictionaryUri' => Utils::uriFromAutocomplete($form_state->getValue('stream_semanticdatadictionary')),
      'hasVersion'                => $form_state->getValue('stream_version') ?? $this->stream->hasVersion,
      'comment'                   => $form_state->getValue('stream_description'),
      'canUpdate'                 => [$email],
      'designedAt'                => $this->stream->designedAt,
      'hasSIRManagerEmail'        => $email,
      'hasStreamStatus'           => $this->stream->hasStreamStatus,
    ];

    if ($form_state->getValue('stream_method') === 'files') {
      $payload['datasetPattern'] = $form_state->getValue('stream_datafile_pattern');
      $payload['cellScopeUri']    = [$form_state->getValue('stream_cell_scope_uri')];
      $payload['cellScopeName']   = [$form_state->getValue('stream_cell_scope_name')];
      $payload['messageProtocol']  = '';
      $payload['messageIP']        = '';
      $payload['messagePort']      = '';
      $payload['messageArchiveId'] = '';
      // $payload['messageHeader']    = '';
    }
    else {
      $payload['messageProtocol']   = $form_state->getValue('stream_protocol');
      $payload['messageIP']         = $form_state->getValue('stream_ip');
      $payload['messagePort']       = $form_state->getValue('stream_port');
      $payload['messageArchiveId']  = $form_state->getValue('stream_archive_id');
      // $payload['messageHeader']     = $form_state->getValue('stream_header');
      $payload['datasetPattern']   = '';
      $payload['cellScopeUri']      = [];
      $payload['cellScopeName']     = [];
      $payload['hasMessageStatus']  = $this->stream->hasMessageStatus ?? HASCO::INACTIVE;
    }

    try {
      $api = \Drupal::service('rep.api_connector');
      // Delete and re-create to perform update.
      $api->elementDel('stream', $this->stream->uri);
      $api->elementAdd('stream', json_encode($payload));
      \Drupal::messenger()->addMessage($this->t('Stream has been updated successfully.'));
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError($this->t('An error occurred while updating the Stream: @msg', ['@msg' => $e->getMessage()]));
    }

    $this->backUrl();
  }

  /**
   * Redirect helper to return to previous page.
   */
  public function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previous = Utils::trackingGetPreviousUrl($uid, 'dpl.edit_stream');
    if ($previous) {
      (new RedirectResponse($previous))->send();
    }
  }

  /**
   * AJAX callback to rebuild the tabs container when method changes.
   */
  public function updateMethodProperties(array &$form, FormStateInterface $form_state) {
    return $form['tabs'];
  }

}
