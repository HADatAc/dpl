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

    $form['#attributes']['novalidate'] = 'novalidate';

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

    // INIT TOPICS
    if (isset($this->getStream()->topics) && is_array($this->getStream()->topics)) {
      $topics_raw = $this->getStream()->topics;
      $topics = [];

      foreach ($topics_raw as $obj) {
        $dpl = $api->parseObjectResponse($api->getUri($obj->deploymentUri), 'getUri');
        $sdd = $api->parseObjectResponse($api->getUri($obj->semanticDataDictionaryUri), 'getUri');

        $topics[] = [
          // “topic” → nome (label) do tópico
          'topic'      => $obj->label ?? '',
          // “deployment” → string de autocomplete (ex: “Label [URI]”)
          'deployment' => Utils::trimAutoCompleteString(
                            // supondo que exista um objeto->deployment, com uri+label
                            $dpl->label  ?? '',
                            $dpl->uri    ?? ''
                          ),
          // “sdd” → mesma lógica para semanticDataDictionary
          'sdd'        => Utils::trimAutoCompleteString(
                            $sdd->label ?? '',
                            $sdd->uri   ?? ''
                          ),
          // “cellscope” → se CellScopeUri vier como array, use o primeiro elemento
          'cellscope'  => is_array($obj->cellScopeUri)
                            ? ($obj->cellScopeUri[0] ?? '')
                            : ($obj->cellScopeUri  ?? ''),
        ];
      }

      // Agora $topics é um array de arrays, cada um com as chaves esperadas
      $form_state->set('topics', $topics);
    }
    else {
      $form_state->set('topics', []);
    }

    //dpm($this->getStream());

    // 2) Prepare the deployment autocomplete label.
    $deploymentLabel = '';
    if (!empty($this->stream->deployment) && isset($this->stream->deployment->uri, $this->stream->deployment->label)) {
      $deploymentLabel = Utils::trimAutoCompleteString(
        $this->stream->deployment->label,
        $this->stream->deployment->uri
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
      '#value' => $this->getStream()->permissionUri ?? HASCO::PUBLIC,
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

      $form['tabs']['tab_content']['tab3']['topics'] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'topics-ajax-wrapper',
          'class' => ['p-3', 'bg-light', 'text-dark', 'row', 'border', 'border-secondary', 'rounded'],
        ],
      ];

      $separator = '<div class="w-100"></div>';

      $form['tabs']['tab_content']['tab3']['topics']['header'] = [
        '#type' => 'markup',
        '#markup' =>
          '<div class="p-2 col bg-secondary text-white border border-white">Topic Name</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Deployment</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Semantic Data Dictionary</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Cell Scope</div>' .
          '<div class="p-2 col-md-1 bg-secondary text-white border border-white">Operations</div>' .
          $separator,
      ];

      $form['tabs']['tab_content']['tab3']['topics']['rows'] = $this->renderTopicRows($topics);

      $form['tabs']['tab_content']['tab3']['topics']['space_3'] = [
        '#type' => 'markup',
        '#markup' => $separator,
      ];

      $form['tabs']['tab_content']['tab3']['topics']['actions'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['col-12', 'mb-3']],
      ];
      $form['tabs']['tab_content']['tab3']['topics']['actions']['add_row'] = [
        '#type' => 'submit',
        '#value' => $this->t('New Topic'),
        '#name'  => 'new_topic',
        '#attributes' =>['class' => ['btn', 'btn-sm', 'add-element-button', 'mt-3']],
        '#ajax'  => [
          'callback' => '::ajaxAddTopicCallback',
          'wrapper'  => 'topics-ajax-wrapper',
          'effect'   => 'fade',
        ],
        '#limit_validation_errors' => [],
        '#submit' => ['::submitAjaxAddTopic'],
      ];

    //
    //  TOPICS: END
    //

    // Header
    $form['tabs']['tab_content']['tab3']['stream_header'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Header'),
      '#default_value' => '',
      // '#required' => ($method === 'messages'),
      '#wrapper_attributes' => [
        'class' => ['mt-3']
      ]
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

    $form_state->set('topics', $topics);

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

      $form_row = [
        'topic' => [
          'top' => [
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ],
          'main' => [
            '#type' => 'textfield',
            '#name' => 'topic_topic_' . $delta,
            '#value' => $topic['topic'],
          ],
          'bottom' => [
            '#type' => 'markup',
            '#markup' => '</div>',
          ],
        ],
        'deployment' => [
          'top' => [
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ],
          'main' => [
            '#type' => 'textfield',
            '#name' => 'topic_deployment_' . $delta,
            '#value' => $topic['deployment'],
            '#autocomplete_route_name' => 'std.deployment_autocomplete',
          ],
          'bottom' => [
            '#type' => 'markup',
            '#markup' => '</div>',
          ],
        ],
        'sdd' => [
          'top' => [
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ],
          'main' => [
            '#type' => 'textfield',
            '#name' => 'topic_sdd_' . $delta,
            '#value' => $topic['sdd'],
            '#autocomplete_route_name' => 'rep.sdd_autocomplete',
          ],
          'bottom' => [
            '#type' => 'markup',
            '#markup' => '</div>',
          ],
        ],
        'cellscope' => [
          'top' => [
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ],
          'main' => [
            '#type' => 'textfield',
            '#name' => 'topic_cellscope_' . $delta,
            '#value' => $topic['cellscope'],
          ],
          'bottom' => [
            '#type' => 'markup',
            '#markup' => '</div>',
          ],
        ],
        'operations' => [
          'top' => [
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col-md-1 border border-white">',
          ],
          'main' => [
            // Use a submit button with AJAX instead of a plain 'button'
            '#type' => 'submit',
            // English comment: This button will trigger AJAX removal of this row.
            '#value' => $this->t('Remove'),
            // English comment: Give a unique name so we can parse which index to remove.
            '#name' => 'topic_remove_' . $delta,
            // English comment: Prevent full form validation before AJAX callback.
            '#limit_validation_errors' => [],
            // English comment: Use a CSS class for styling or JS hooks if needed.
            '#attributes' => [
              'class' => ['remove-row', 'btn', 'btn-sm', 'delete-element-button'],
              // 'id' is optional here since '#name' is already unique.
            ],
            // English comment: Attach AJAX settings so that only this region is re-rendered.
            '#ajax' => [
              'callback' => '::ajaxRemoveTopicCallback',
              'wrapper' => 'topics-ajax-wrapper',
              'effect' => 'fade',
            ],
            // English comment: Define a submit handler that actually removes the topic.
            '#submit' => ['::submitAjaxRemoveTopic'],
          ],
          'bottom' => [
            '#type' => 'markup',
            '#markup' => '</div>' . $separator,
          ],
        ],
      ];

      $rowId = 'row' . $delta;
      $form_rows[] = [
        $rowId => $form_row,
      ];
    }

    return $form_rows;
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
   * Submission AJAX Handler to add a new topic.
   */
  public function submitAjaxAddTopic(array &$form, FormStateInterface $form_state) {
    $topics = $form_state->get('topics') ?: [];

    $input = $form_state->getUserInput();
    foreach ($topics as $delta => &$topicItem) {
      $topicItem['topic']      = $input['topic_topic_' . $delta]      ?? $topicItem['topic'];
      $topicItem['deployment'] = $input['topic_deployment_' . $delta] ?? $topicItem['deployment'];
      $topicItem['sdd']        = $input['topic_sdd_' . $delta]        ?? $topicItem['sdd'];
      $topicItem['cellscope']  = $input['topic_cellscope_' . $delta]  ?? $topicItem['cellscope'];
    }
    unset($topicItem);

    $topics[] = [
      'topic'     => '',
      'deployment'=> '',
      'sdd'       => '',
      'cellscope' => '',
    ];

    $form_state->set('topics', $topics);
    $form_state->setRebuild(TRUE);
  }

  /**
   * AJAX callback to rebuild only Topic block.
   */
  public function ajaxAddTopicCallback(array &$form, FormStateInterface $form_state) {
    return $form['tabs']['tab_content']['tab3']['topics'];
  }

  /**
   * AJAX Handler for Topic row delete.
   */
  public function submitAjaxRemoveTopic(array &$form, FormStateInterface $form_state) {
    $topics = $form_state->get('topics') ?: [];

    $input = $form_state->getUserInput();
    foreach ($topics as $delta => &$topicItem) {
      $topicItem['topic']      = $input['topic_topic_' . $delta]      ?? $topicItem['topic'];
      $topicItem['deployment'] = $input['topic_deployment_' . $delta] ?? $topicItem['deployment'];
      $topicItem['sdd']        = $input['topic_sdd_' . $delta]        ?? $topicItem['sdd'];
      $topicItem['cellscope']  = $input['topic_cellscope_' . $delta]  ?? $topicItem['cellscope'];
    }
    unset($topicItem);

    $trigger = $form_state->getTriggeringElement();
    $name = $trigger['#name'];
    $parts = explode('_', $name);
    $index_to_remove = (int) end($parts);

    if (isset($topics[$index_to_remove])) {
      unset($topics[$index_to_remove]);
      $topics = array_values($topics);
    }

    $form_state->set('topics', $topics);
    $form_state->setRebuild(TRUE);
  }

  /**
   * AJAX callback for rebuild only the Topic list after topic removal.
   */
  public function ajaxRemoveTopicCallback(array &$form, FormStateInterface $form_state) {
    $topics = $form_state->get('topics') ?: [];

    $input = $form_state->getUserInput();
    foreach ($topics as $delta => &$topicItem) {
      $topicItem['topic']      = $input['topic_topic_' . $delta]      ?? $topicItem['topic'];
      $topicItem['deployment'] = $input['topic_deployment_' . $delta] ?? $topicItem['deployment'];
      $topicItem['sdd']        = $input['topic_sdd_' . $delta]        ?? $topicItem['sdd'];
      $topicItem['cellscope']  = $input['topic_cellscope_' . $delta]  ?? $topicItem['cellscope'];
    }
    unset($topicItem);

    $trigger = $form_state->getTriggeringElement();
    $id = $trigger['#id'];
    $index_to_remove = (int) str_replace('topic-', '', $id);

    if (isset($topics[$index_to_remove])) {
      unset($topics[$index_to_remove]);
      $topics = array_values($topics);
    }

    $form_state->set('topics', $topics);
    $form_state->setRebuild(TRUE);

    return $form['tabs']['tab_content']['tab3']['topics'];
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
