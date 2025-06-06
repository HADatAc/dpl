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

    // INIT TOPICS
    $topics = [];

    //dpm($this->getStream());

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

    // SET SEPARATOR
    $separator = '<div class="w-100"></div>';


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
      '#default_value' => $this->stream->datasetPattern ?? '',
      '#required' => ($method === 'files'),
    ];
    // Deployment field.
    $form['tabs']['tab_content']['tab2']['stream_deployment'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Deployment'),
      '#default_value' => $deploymentLabel,
      '#autocomplete_route_name' => 'std.deployment_autocomplete',
      '#required' => TRUE,
    ];
    // SDD autocomplete.
    $form['tabs']['tab_content']['tab2']['stream_semanticdatadictionary'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Semantic Data Dictionary'),
      '#autocomplete_route_name' => 'std.semanticdatadictionary_autocomplete',
      '#default_value' => Utils::fieldToAutocomplete($this->getStream()->semanticDataDictionaryUri, $this->getStream()->semanticDataDictionary->label),
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

    //  
    //  TOPICS: START
    //

      $form['tabs']['tab_content']['tab3']['topics_title'] = [
        '#type' => 'markup',
        '#markup' => 'Topics',
      ];

      $form['tabs']['tab_content']['tab3']['topics'] = array(
        '#type' => 'container',
        '#title' => $this->t('topics'),
        '#attributes' => array(
          'class' => array('p-3', 'bg-light', 'text-dark', 'row', 'border', 'border-secondary', 'rounded'),
          'id' => 'custom-table-wrapper',
        ),
      );

      $form['tabs']['tab_content']['tab3']['topics']['header'] = array(
        '#type' => 'markup',
        '#markup' =>
          '<div class="p-2 col bg-secondary text-white border border-white">Topic Name</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Deployment</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Semantic Data Dictionary</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Cell Scope</div>' .
          '<div class="p-2 col-md-1 bg-secondary text-white border border-white">Operations</div>' . $separator,
      );

      $form['tabs']['tab_content']['tab3']['topics']['rows'] = $this->renderTopicRows($topics);

      $form['tabs']['tab_content']['tab3']['topics']['space_3'] = [
        '#type' => 'markup',
        '#markup' => $separator,
      ];

      $form['tabs']['tab_content']['tab3']['topics']['actions']['top'] = array(
        '#type' => 'markup',
        '#markup' => '<div class="p-3 col">',
      );

      $form['tabs']['tab_content']['tab3']['topics']['actions']['add_row'] = [
        '#type' => 'submit',
        '#value' => $this->t('New Topic'),
        '#name' => 'new_topic',
        '#attributes' => array('class' => array('btn', 'btn-sm', 'add-element-button')),
      ];

      $form['tabs']['tab_content']['tab3']['topics']['actions']['bottom'] = array(
        '#type' => 'markup',
        '#markup' => '</div>' . $separator,
      );

    //  
    //  TOPICS: END
    //

    $form['tabs']['tab_content']['tab3']['space_0'] = [
      '#type' => 'item',
      '#markup' => '<br>',
    ];

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

    $form['space_1'] = [
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

  /******************************
   *
   *    TOPIC'S FUNCTIONS
   *
   ******************************/

   protected function renderTopicRows(array $topics) {
    $form_rows = [];
    $separator = '<div class="w-100"></div>';
    foreach ($topics as $delta => $topic) {

      $form_row = array(
        'topic' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ),
          'main' => array(
            '#type' => 'textfield',
            '#name' => 'topic_topic_' . $delta,
            '#value' => $topic['topic'],
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>',
          ),
        ),
        'deployment' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ),
          'main' => array(
            '#type' => 'textfield',
            '#name' => 'topic_deployment_' . $delta,
            '#value' => $topic['deployment'],
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>',
          ),
        ),
        'sdd' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ),
          'main' => array(
            '#type' => 'textfield',
            '#name' => 'topic_sdd_' . $delta,
            '#value' => $topic['sdd'],
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>',
          ),
        ),
        'cellscope' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ),
          'main' => array(
            '#type' => 'textfield',
            '#name' => 'topic_cellscope_' . $delta,
            '#value' => $topic['cellscope'],
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>',
          ),
        ),
        'operations' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col-md-1 border border-white">',
          ),
          'main' => array(
            '#type' => 'submit',
            '#name' => 'topic_remove_' . $delta,
            '#value' => $this->t('Remove'),
            '#attributes' => array(
              'class' => array('remove-row', 'btn', 'btn-sm', 'delete-element-button'),
              'id' => 'topic-' . $delta,
            ),
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>' . $separator,
          ),
        ),
      );

      $rowId = 'row' . $delta;
      $form_rows[] = [
        $rowId => $form_row,
      ];

    }
    return $form_rows;
  }

  protected function updateTopics(FormStateInterface $form_state) {
    $topics = \Drupal::state()->get('my_form_topics');
    $input = $form_state->getUserInput();
    if (isset($input) && is_array($input) &&
        isset($topics) && is_array($topics)) {

      foreach ($topics as $topic_id => $topic) {
        if (isset($topic_id) && isset($topic)) {
          $codes[$topic_id]['topic']       = $input['topic_topic_' . $topic_id] ?? '';
          $codes[$topic_id]['deployment']  = $input['topic_deployment_' . $topic_id] ?? '';
          $codes[$topic_id]['sdd']         = $input['topic_sdd_' . $topic_id] ?? '';
          $codes[$topic_id]['cellscope']   = $input['topic_cellscope_' . $topic_id] ?? '';
        }
      }
    }
    \Drupal::state()->set('my_form_codes', $topics);
    return;
  }

  protected function saveTopics($semanticDataDictionaryUri, array $codes) {
    if (!isset($semanticDataDictionaryUri)) {
      \Drupal::messenger()->addError(t("No semantic data dictionary's URI have been provided to save possible values."));
      return;
    }
    if (!isset($codes) || !is_array($codes)) {
      \Drupal::messenger()->addWarning(t("Semantic data dictionary has no possible values to be saved."));
      return;
    }

    foreach ($codes as $code_id => $code) {
      if (isset($code_id) && isset($code)) {
        try {
          $useremail = \Drupal::currentUser()->getEmail();

          $column = ' ';
          if ($codes[$code_id]['column'] != NULL && $codes[$code_id]['column'] != '') {
            $column = $codes[$code_id]['column'];
          }

          $codeStr = ' ';
          if ($codes[$code_id]['code'] != NULL && $codes[$code_id]['code'] != '') {
            $codeStr = $codes[$code_id]['code'];
          }

          $codeLabel = ' ';
          if ($codes[$code_id]['label'] != NULL && $codes[$code_id]['label'] != '') {
            $codeLabel = $codes[$code_id]['label'];
          }

          $class = ' ';
          if ($codes[$code_id]['class'] != NULL && $codes[$code_id]['class'] != '') {
            $class = $codes[$code_id]['class'];
          }

          $codeUri = str_replace(
            Constant::PREFIX_SEMANTIC_DATA_DICTIONARY,
            Constant::PREFIX_POSSIBLE_VALUE,
            $semanticDataDictionaryUri) . '/' . $code_id;
          $codeJSON = '{"uri":"'. $codeUri .'",'.
              '"superUri":"'.HASCO::POSSIBLE_VALUE.'",'.
              '"hascoTypeUri":"'.HASCO::POSSIBLE_VALUE.'",'.
              '"partOfSchema":"'.$semanticDataDictionaryUri.'",'.
              '"listPosition":"'.$code_id.'",'.
              '"isPossibleValueOf":"'.$column.'",'.
              '"label":"'.$column.'",'.
              '"hasCode":"' . $codeStr . '",' .
              '"hasCodeLabel":"' . $codeLabel . '",' .
              '"hasClass":"' . $class . '",' .
              '"comment":"Possible value ' . $column . ' of ' . $column . ' of SDD ' . $semanticDataDictionaryUri . '",'.
              '"hasSIRManagerEmail":"'.$useremail.'"}';
          $api = \Drupal::service('rep.api_connector');
          $api->elementAdd('possiblevalue',$codeJSON);

          //dpm($codeJSON);

        } catch(\Exception $e){
          \Drupal::messenger()->addError(t("An error occurred while saving possible value(s): ".$e->getMessage()));
        }
      }
    }
    return;
  }

  public function addTopicRow() {

    // Add a new row to the table.
    $topics[] = [
      'topic' => '',
      'deployment' => '',
      'sdd' => '',
      'cellscope' => '',
    ];

    // Rebuild the table rows.
    $form['topics']['rows'] = $this->renderTopicRows($topics);
    return;
  }

  public function removeTopicRow($button_name) {
    $topics = \Drupal::state()->get('my_form_topics') ?? [];

    // from button name's value, determine which row to remove.
    $parts = explode('_', $button_name);
    $topic_to_remove = (isset($parts) && is_array($parts)) ? (int) (end($parts)) : null;

    if (isset($topic_to_remove) && $topic_to_remove > -1) {
      unset($topics[$topic_to_remove]);
      $topics = array_values($topics);
      \Drupal::state()->set('my_form_topics', $topics);
    }
    return;
  }

  /**
   * {@inheritdoc}
   *
   * Handles submit: updates via API or redirects on cancel.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // IDENTIFY NAME OF BUTTON triggering submitForm()
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    // RETRIEVE TOPICS STATE
    $topics = \Drupal::state()->get('my_form_topics');
    
    // PROCESS BUTTONS

    if ($button_ === 'back') {
      $this->backUrl();
      return;
    }

    if ($button_name === 'new_topic') {
      $this->addTopicRow();
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
