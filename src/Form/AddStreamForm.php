<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\HASCO;

/**
 * Form AddStreamForm.
 *
 * Provides a form to create a Stream entity with two dynamic tabs:
 * - File-Method Properties (only when method = 'files')
 * - Message-Method Properties (only when method = 'messages')
 * Adds dynamic "Topics" functionality in the Messages tab, mirroring EditStreamForm.
 */
class AddStreamForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_stream_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Attach tabs and states libraries.
    $form['#attached']['library'][] = 'dpl/dpl_onlytabs';
    $form['#attached']['library'][] = 'core/drupal.states';
    $form['#attached']['library'][] = 'core/jquery.once';

    // Study Prefered name
    $preferred_study = \Drupal::config('rep.settings')->get('preferred_study') ?? 'study';

    // 1) Initializes "topics" in form_state.
    if ($form_state->has('topics')) {
      $topics = $form_state->get('topics');
    }
    else {
      $topics = [];
      $form_state->set('topics', $topics);
    }
    if (!is_array($topics)) {
      $topics = [];
      $form_state->set('topics', $topics);
    }

    // 2) Recovers "selected_method" saved in form_state or, if not,
    //    gets the value from getValue('stream_method'). If none exists,
    //    uses 'files' as default.
    if ($form_state->hasValue('stream_method')) {
      $method = $form_state->getValue('stream_method');
    }
    elseif ($form_state->has('selected_method')) {
      $method = $form_state->get('selected_method');
    }
    else {
      $method = 'files';
    }
    // Saves back for next rebuild.
    $form_state->set('selected_method', $method);

    // 2b) Recovers "selected_protocol" for field visibility control.
    if ($form_state->hasValue('stream_protocol')) {
      $protocol = $form_state->getValue('stream_protocol');
    }
    elseif ($form_state->has('selected_protocol')) {
      $protocol = $form_state->get('selected_protocol');
    }
    else {
      $protocol = 'MQTT';
    }
    // Saves back for next rebuild.
    $form_state->set('selected_protocol', $protocol);

    // 3) AJAX container that wraps the three tabs.
    $form['tabs'] = [
      '#type' => 'container',
      '#prefix' => '<div id="method-properties-wrapper">',
      '#suffix' => '</div>',
      '#attributes' => ['class' => ['tabs']],
    ];

    // 4) Navigation links for tabs.
    $form['tabs']['tab_links'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['nav', 'nav-tabs']],
    ];
    // Tab 1: Basic Properties (active when method != messages).
    $form['tabs']['tab_links']['basic'] = [
      '#type' => 'html_tag',
      '#tag' => 'li',
      '#attributes' => ['class' => ['nav-item']],
      '#value' => '<a class="nav-link' . ($method !== 'messages' ? ' active' : '') . '" data-toggle="tab" href="#edit-tab1">' .
        $this->t('Basic Properties') .
        '</a>',
    ];
    // Tab 2: File-Method (only if $method === 'files').
    $form['tabs']['tab_links']['file'] = [
      '#type'   => 'html_tag',
      '#tag'    => 'li',
      '#access' => ($method === 'files'),
      '#attributes' => ['class' => ['nav-item']],
      '#value' => '<a class="nav-link" data-toggle="tab" href="#edit-tab2">' .
        $this->t('File-Method Properties') .
        '</a>',
    ];
    // Tab 3: Message-Method (only if $method === 'messages').
    $form['tabs']['tab_links']['message'] = [
      '#type'   => 'html_tag',
      '#tag'    => 'li',
      '#access' => ($method === 'messages'),
      '#attributes' => ['class' => ['nav-item']],
      '#value' => '<a class="nav-link' . ($method === 'messages' ? ' active' : '') . '" data-toggle="tab" href="#edit-tab3">' .
        $this->t('Message-Method Properties') .
        '</a>',
    ];

    // 5) Content container for tabs.
    $form['tabs']['tab_content'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tab-content']],
    ];

    // === Tab 1: Basic Properties ===
    $form['tabs']['tab_content']['tab1'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => array_merge(['tab-pane', 'p-3', 'border', 'border-light'], $method !== 'messages' ? ['active'] : []),
        'id'    => 'edit-tab1',
      ],
    ];
    // Select "Method" with AJAX for tab rebuild.
    $form['tabs']['tab_content']['tab1']['stream_method'] = [
      '#type' => 'select',
      '#title' => $this->t('Method'),
      '#options' => [
        'files'    => $this->t('Files'),
        'messages' => $this->t('Messages'),
      ],
      '#default_value' => $method,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateMethodProperties',
        'event'    => 'change',
        'wrapper'  => 'method-properties-wrapper',
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
      '#default_value' => HASCO::PUBLIC,
      '#required' => TRUE,
    ];
    // Study autocomplete.
    $form['tabs']['tab_content']['tab1']['stream_study'] = [
      '#type' => 'textfield',
      '#title' => $this->t($preferred_study),
      '#autocomplete_route_name' => 'std.study_autocomplete',
      '#required' => TRUE,
    ];
    // Version (fixo) e Description.
    $form['tabs']['tab_content']['tab1']['stream_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#value' => 1,
      '#disabled' => TRUE,
    ];
    $form['tabs']['tab_content']['tab1']['stream_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
    ];

    // === Tab 2: File-Method Properties ===
    $form['tabs']['tab_content']['tab2'] = [
      '#type'   => 'container',
      '#access' => ($method === 'files'),
      '#attributes' => [
        'class' => ['tab-pane', 'p-3', 'border', 'border-light'],
        'id'    => 'edit-tab2',
      ],
    ];
    // Datafile Pattern.
    $form['tabs']['tab_content']['tab2']['stream_datafile_pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Datafile Pattern'),
      '#required' => ($method === 'files'),
    ];
    // Deployment (autocomplete).
    $form['tabs']['tab_content']['tab2']['stream_deployment'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Deployment'),
      '#autocomplete_route_name' => 'std.deployment_autocomplete',
      '#required' => TRUE,
    ];
    // Semantic Data Dictionary (autocomplete).
    $form['tabs']['tab_content']['tab2']['stream_semanticdatadictionary'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Semantic Data Dictionary'),
      '#autocomplete_route_name' => 'std.semanticdatadictionary_autocomplete',
    ];
    // Cell Scope URI.
    $form['tabs']['tab_content']['tab2']['stream_cell_scope_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cell Scope URI'),
      // '#required' => ($method === 'files'),
    ];
    // Cell Scope Name.
    $form['tabs']['tab_content']['tab2']['stream_cell_scope_name'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Cell Scope Name'),
      // '#required' => ($method === 'files'),
    ];

    // === Tab 3: Message-Method Properties ===
    $form['tabs']['tab_content']['tab3'] = [
      '#type'   => 'container',
      '#access' => ($method === 'messages'),
      '#attributes' => [
        'class' => array_merge(['tab-pane', 'p-3', 'border', 'border-light'], $method === 'messages' ? ['active'] : []),
        'id'    => 'edit-tab3',
      ],
    ];
    // Protocol select with AJAX.
    $form['tabs']['tab_content']['tab3']['stream_protocol'] = [
      '#type' => 'select',
      '#title' => $this->t('Protocol'),
      '#options' => [
        'MQTT' => 'MQTT',
        'HTML' => 'HTML',
        'ROS' => 'ROS',
        'RestFULL' => 'RestFULL',
        'OPC-UA' => 'OPC-UA',
      ],
      '#default_value' => $protocol,
      '#required' => ($method === 'messages'),
      '#ajax' => [
        'callback' => '::updateProtocolProperties',
        'event'    => 'change',
        'wrapper'  => 'method-properties-wrapper',
      ],
    ];
    // IP/URL field.
    $form['tabs']['tab_content']['tab3']['stream_ip'] = [
      '#type' => 'textfield',
      '#title' => ($protocol === 'RestFULL') ? $this->t('URL') : $this->t('IP'),
      '#required' => ($method === 'messages'),
    ];
    // Port field (hidden for RestFULL).
    $form['tabs']['tab_content']['tab3']['stream_port'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Port'),
      '#required' => ($method === 'messages' && $protocol !== 'RestFULL'),
      '#access' => ($protocol !== 'RestFULL'),
    ];
    // Archive ID.
    $form['tabs']['tab_content']['tab3']['stream_archive_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Archive ID'),
      '#required' => ($method === 'messages'),
    ];

    // Deployment and Semantic Data Dictionary (only for RestFULL).
    $form['tabs']['tab_content']['tab3']['stream_deployment_restfull'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Deployment'),
      '#autocomplete_route_name' => 'std.deployment_autocomplete',
      '#access' => ($method === 'messages' && $protocol === 'RestFULL'),
      '#required' => ($method === 'messages' && $protocol === 'RestFULL'),
    ];
    $form['tabs']['tab_content']['tab3']['stream_semanticdatadictionary_restfull'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Semantic Data Dictionary'),
      '#autocomplete_route_name' => 'std.semanticdatadictionary_autocomplete',
      '#access' => ($method === 'messages' && $protocol === 'RestFULL'),
      '#required' => ($method === 'messages' && $protocol === 'RestFULL'),
    ];

    //
    // === TOPICS SECTION (only if method = messages and protocol != RestFULL) ===
    //
    $topicsTitle = ($protocol === 'OPC-UA') ? $this->t('Objects') : $this->t('Topics');
    $form['tabs']['tab_content']['tab3']['topics_title'] = [
      '#type' => 'markup',
      '#markup' => '<h4 class="mt-4">' . $topicsTitle . '</h4>',
      '#access' => ($method === 'messages' && $protocol !== 'RestFULL'),
    ];

    // Container that will be replaced via AJAX (hidden for RestFULL).
    $form['tabs']['tab_content']['tab3']['topics'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'topics-ajax-wrapper',
        'class' => [
          'p-3', 'bg-light', 'text-dark', 'row',
          'border', 'border-secondary', 'rounded',
        ],
      ],
      '#access'   => ($method === 'messages' && $protocol !== 'RestFULL'),
      '#attached' => [],  // Ensures it's not null in the AJAX response.
    ];

    $separator = '<div class="w-100"></div>';

    // Table header for Topics (label changes to OPC-UA).
    $topicLabel = ($protocol === 'OPC-UA') ? $this->t('Object') : $this->t('Topic Name');
    $form['tabs']['tab_content']['tab3']['topics']['header'] = [
      '#type' => 'markup',
      '#markup' =>
        '<div class="p-2 col bg-secondary text-white border border-white">' . $topicLabel . '</div>' .
        '<div class="p-2 col bg-secondary text-white border border-white">' . $this->t('Deployment') . '</div>' .
        '<div class="p-2 col bg-secondary text-white border border-white">' . $this->t('Semantic Data Dictionary') . '</div>' .
        '<div class="p-2 col bg-secondary text-white border border-white">' . $this->t('Cell Scope') . '</div>' .
        '<div class="p-2 col-md-1 bg-secondary text-white border border-white">' . $this->t('Operations') . '</div>' .
        $separator,
    ];

    // Existing "topics" rows.
    $form['tabs']['tab_content']['tab3']['topics']['rows'] = $this->renderTopicRows($topics);

    // Extra space.
    $form['tabs']['tab_content']['tab3']['topics']['space_3'] = [
      '#type' => 'markup',
      '#markup' => $separator,
    ];

    // "New Topic" button (AJAX).
    $form['tabs']['tab_content']['tab3']['topics']['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['col-12', 'mb-3']],
    ];
    $form['tabs']['tab_content']['tab3']['topics']['actions']['add_row'] = [
      '#type' => 'submit',
      '#value' => $this->t('New Topic'),
      '#name'  => 'new_topic',
      '#attributes' => ['class' => ['btn', 'btn-sm', 'add-element-button', 'mt-3']],
      '#ajax'  => [
        'callback' => '::ajaxAddTopicCallback',
        'wrapper'  => 'topics-ajax-wrapper',
        'effect'   => 'fade',
      ],
      '#limit_validation_errors' => [],
      '#submit' => ['::submitAjaxAddTopic'],
    ];

    // "Header" extra (only for messages).
    $form['tabs']['tab_content']['tab3']['stream_header'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Header'),
      '#access' => ($method === 'messages'),
      '#wrapper_attributes' => [
        'class' => ['mt-3']
      ]
    ];

    //
    // === FINAL BUTTONS: Save / Cancel ===
    //
    $form['save_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#attributes' => ['class' => ['btn', 'btn-primary', 'save-button']],
    ];
    $form['cancel_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#submit' => ['::backUrl'],
      '#limit_validation_errors' => [],
      '#attributes' => ['class' => ['btn', 'btn-danger', 'cancel-button']],
    ];

    $form['space_0'] = [
      '#type' => 'item',
      '#markup' => '<br><br>',
    ];

    // Persist the array of topics for future rebuilds.
    $form_state->set('topics', $topics);

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Validates mandatory fields according to the selected method.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    
    $study = $form_state->getValue('stream_study');
    if (empty($study)) {
      $form_state->setErrorByName('stream_study', $this->t('Study is mandatory.'));
    }
    $method = $form_state->getValue('stream_method');
    if ($method === 'files') {
      foreach ([
        'stream_datafile_pattern' => $this->t('Datafile Pattern'),
      ] as $key => $label) {
        if (empty($form_state->getValue($key))) {
          $form_state->setErrorByName($key, $this->t('@label is mandatory for Files method.', ['@label' => $label]));
        }
      }
    }
    elseif ($method === 'messages') {
      $protocol = $form_state->getValue('stream_protocol');
      // Basic validation for all protocols.
      foreach ([
        'stream_protocol'    => $this->t('Protocol'),
        'stream_ip'          => $this->t('IP'),
        'stream_archive_id'  => $this->t('Archive ID'),
      ] as $key => $label) {
        if (empty($form_state->getValue($key))) {
          $form_state->setErrorByName($key, $this->t('@label is mandatory for Messages method.', ['@label' => $label]));
        }
      }
      // Port is not mandatory for RestFULL.
      if ($protocol !== 'RestFULL' && empty($form_state->getValue('stream_port'))) {
        $form_state->setErrorByName('stream_port', $this->t('Port is mandatory for Messages method.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * Submit handler final: saves Stream or cancels.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement()['#name'];
    $method = $form_state->getValue('stream_method');
    if ($trigger === 'back') {
      $this->backUrl();
      return;
    }

    // Retrieves and updates "topics" values before building the payload.
    $topics = $form_state->get('topics') ?: [];
    $input = $form_state->getUserInput();
    foreach ($topics as $delta => &$topicItem) {
      $topicItem['topic']      = $input['topic_topic_' . $delta]      ?? $topicItem['topic'];
      $topicItem['deployment'] = $input['topic_deployment_' . $delta] ?? $topicItem['deployment'];
      $topicItem['sdd']        = $input['topic_sdd_' . $delta]        ?? $topicItem['sdd'];
      $topicItem['cellscope']  = $input['topic_cellscope_' . $delta]  ?? $topicItem['cellscope'];
    }
    unset($topicItem);

    // Builds date/time and other fields.
    $now = new \DateTime();
    $timestamp = $now->format('Y-m-d\TH:i:s') . '.' . $now->format('v') . $now->format('O');
    $deployment = Utils::uriFromAutocomplete($form_state->getValue('stream_deployment'));
    $email = \Drupal::currentUser()->getEmail();
    $uri = Utils::uriGen('stream');

    $stream = [
      'uri'                       => $uri,
      'typeUri'                   => HASCO::STREAM,
      'hascoTypeUri'              => HASCO::STREAM,
      'label'                     => 'Stream',
      'method'                    => $method,
      'permissionUri'             => $form_state->getValue('permission_uri'),
      'hasVersion'                => $form_state->getValue('stream_version') ?? 1,
      'comment'                   => $form_state->getValue('stream_description'),
      'canUpdate'                 => [$email],
      'designedAt'                => $timestamp,
      'studyUri'                  => Utils::uriFromAutocomplete($form_state->getValue('stream_study')),
      'hasSIRManagerEmail'        => $email,
      'hasStreamStatus'           => HASCO::DRAFT,
    ];

    if ($method === 'files') {
      $stream['datasetPattern']   = $form_state->getValue('stream_datafile_pattern');
      $stream['deploymentUri']    = $deployment;
      $stream['semanticDataDictionaryUri'] = Utils::uriFromAutocomplete($form_state->getValue('stream_semanticdatadictionary'));
      $stream['cellScopeUri']     = [$form_state->getValue('stream_cell_scope_uri')];
      $stream['cellScopeName']    = [$form_state->getValue('stream_cell_scope_name')];
      $stream['messageProtocol']  = '';
      $stream['messageIP']        = '';
      $stream['messagePort']      = '';
      $stream['messageArchiveId'] = '';
    }
    else {
      $stream['messageProtocol']   = $form_state->getValue('stream_protocol');
      $stream['messageIP']         = $form_state->getValue('stream_ip');
      $stream['messagePort']       = $form_state->getValue('stream_port');
      $stream['messageArchiveId']  = $form_state->getValue('stream_archive_id');
      $stream['datasetPattern']    = '';
      $stream['cellScopeUri']      = [];
      $stream['cellScopeName']     = [];
      if ($stream['messageProtocol'] === 'RestFULL') {
        $stream['deploymentUri']             = Utils::uriFromAutocomplete($form_state->getValue('stream_deployment_restfull'));
        $stream['semanticDataDictionaryUri'] = Utils::uriFromAutocomplete($form_state->getValue('stream_semanticdatadictionary_restfull'));
      }
    }

    try {
      \Drupal::service('rep.api_connector')
        ->elementAdd('stream', json_encode($stream, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

      // Attach "topics" only if at least one exists.
      if (!empty($topics)) {

        foreach ($topics as $topicItem) {

          $uriTopic = Utils::uriGen('streamtopic');
          $streamTopic = [
            'uri'                       => $uriTopic,
            'typeUri'                   => HASCO::STREAMTOPIC,
            'hascoTypeUri'              => HASCO::STREAMTOPIC,
            'streamUri'                 => $stream['uri'],
            'label'                     => $topicItem['topic'],
            'deploymentUri'             => Utils::uriFromAutocomplete($topicItem['deployment']),
            'semanticDataDictionaryUri' => Utils::uriFromAutocomplete($topicItem['sdd']),
            'cellScopeUri'              => [$topicItem['cellscope']],
            'hasTopicStatus'            => HASCO::INACTIVE,
          ];

          \Drupal::service('rep.api_connector')
            ->elementAdd('streamtopic', json_encode($streamTopic, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
      }

      \Drupal::messenger()->addMessage($this->t('Stream has been added successfully.'));

    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError($this->t('Error adding Stream: @msg', ['@msg' => $e->getMessage()]));
    }

    // Redirect back.
    $this->backUrl();
  }

  /**
   * AJAX callback for rebuild tabs when "Method" changes.
   */
  public function updateMethodProperties(array &$form, FormStateInterface $form_state) {
    return $form['tabs'];
  }

  /**
   * AJAX callback for rebuild when "Protocol" changes.
   */
  public function updateProtocolProperties(array &$form, FormStateInterface $form_state) {
    return $form['tabs'];
  }

  /******************************
   *
   *    TOPICS FUNCTIONS
   *
   ******************************/

  /**
   * Renders each Topic row in the topics container.
   *
   * @param array $topics
   *   Array of topics saved in form_state.
   *
   * @return array
   *   Render array of topic rows.
   */
  protected function renderTopicRows(array $topics) {
    $form_rows = [];
    $separator = '<div class="w-100"></div>';

    foreach ($topics as $delta => $topic) {
      $form_row = [
        'topic' => [
          'top' => [
            '#type'   => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ],
          'main' => [
            '#type'  => 'textfield',
            '#name'  => 'topic_topic_' . $delta,
            '#value' => $topic['topic'],
          ],
          'bottom' => [
            '#type'   => 'markup',
            '#markup' => '</div>',
          ],
        ],
        'deployment' => [
          'top' => [
            '#type'   => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ],
          'main' => [
            '#type'  => 'textfield',
            '#name'  => 'topic_deployment_' . $delta,
            '#value' => $topic['deployment'],
            '#autocomplete_route_name' => 'std.deployment_autocomplete',
          ],
          'bottom' => [
            '#type'   => 'markup',
            '#markup' => '</div>',
          ],
        ],
        'sdd' => [
          'top' => [
            '#type'   => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ],
          'main' => [
            '#type'  => 'textfield',
            '#name'  => 'topic_sdd_' . $delta,
            '#value' => $topic['sdd'],
            '#autocomplete_route_name' => 'std.semanticdatadictionary_autocomplete',
          ],
          'bottom' => [
            '#type'   => 'markup',
            '#markup' => '</div>',
          ],
        ],
        'cellscope' => [
          'top' => [
            '#type'   => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ],
          'main' => [
            '#type'  => 'textfield',
            '#name'  => 'topic_cellscope_' . $delta,
            '#value' => $topic['cellscope'],
          ],
          'bottom' => [
            '#type'   => 'markup',
            '#markup' => '</div>',
          ],
        ],
        'operations' => [
          'top' => [
            '#type'   => 'markup',
            '#markup' => '<div class="pt-3 col-md-1 border border-white">',
          ],
          'main' => [
            '#type' => 'submit',
            '#value' => $this->t('Remove'),
            '#name' => 'topic_remove_' . $delta,
            '#limit_validation_errors' => [],
            '#attributes' => [
              'class' => ['remove-row', 'btn', 'btn-sm', 'delete-element-button'],
            ],
            '#ajax' => [
              'callback' => '::ajaxRemoveTopicCallback',
              'wrapper'  => 'topics-ajax-wrapper',
              'effect'   => 'fade',
            ],
            '#submit' => ['::submitAjaxRemoveTopic'],
          ],
          'bottom' => [
            '#type'   => 'markup',
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
   * AJAX submit handler for adding new topic row.
   */
  public function submitAjaxAddTopic(array &$form, FormStateInterface $form_state) {
    $topics = $form_state->get('topics') ?: [];

    // Preserves current values before adding new row.
    $input = $form_state->getUserInput();
    foreach ($topics as $delta => &$topicItem) {
      $topicItem['topic']      = $input['topic_topic_' . $delta]      ?? $topicItem['topic'];
      $topicItem['deployment'] = $input['topic_deployment_' . $delta] ?? $topicItem['deployment'];
      $topicItem['sdd']        = $input['topic_sdd_' . $delta]        ?? $topicItem['sdd'];
      $topicItem['cellscope']  = $input['topic_cellscope_' . $delta]  ?? $topicItem['cellscope'];
    }
    unset($topicItem);

    // Appends an empty row to the array.
    $topics[] = [
      'topic'      => '',
      'deployment' => '',
      'sdd'        => '',
      'cellscope'  => '',
    ];

    $form_state->set('topics', $topics);
    $form_state->setRebuild(TRUE);
  }

  /**
   * AJAX callback: returns the "topics" container (updated).
   */
  public function ajaxAddTopicCallback(array &$form, FormStateInterface $form_state) {
    $build = $form['tabs']['tab_content']['tab3']['topics'];
    if (!isset($build['#attached']) || !is_array($build['#attached'])) {
      $build['#attached'] = [];
    }
    return $build;
  }

  /**
   * AJAX submit handler for removing a topic row.
   */
  public function submitAjaxRemoveTopic(array &$form, FormStateInterface $form_state) {
    $topics = $form_state->get('topics') ?: [];

    // Preserves values, except the one being removed.
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
   * AJAX callback: returns the "topics" container after removal.
   */
  public function ajaxRemoveTopicCallback(array &$form, FormStateInterface $form_state) {
    $build = $form['tabs']['tab_content']['tab3']['topics'];
    if (!isset($build['#attached']) || !is_array($build['#attached'])) {
      $build['#attached'] = [];
    }
    return $build;
  }

  /**
   * Redirect helper to the manage_streams_route.
   */
  public function backUrl() {
    // $url = Url::fromRoute('dpl.manage_streams_route');
    // $url->setRouteParameter('state', 'design');
    // $url->setRouteParameter('page', '1');
    // $url->setRouteParameter('pagesize', '10');
    // (new RedirectResponse($url->toString()))->send();
    $uid = \Drupal::currentUser()->id();
    $previous = Utils::trackingGetPreviousUrl($uid, 'dpl.add_stream');
    if ($previous) {
      (new RedirectResponse($previous))->send();
    }
  }

}
