<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\HASCO;

/**
 * Provides a form to edit an existing Stream entity with dynamic tabs:
 * - Basic Properties (always visible)
 * - File-Method Properties (only when method = 'files')
 * - Message-Method Properties (only when method = 'messages')
 *
 * Uses server-side #access and AJAX to rebuild tabs when the method changes.
 */
class EditStreamForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_stream_form';
  }

  /**
   * The Stream object loaded from the API.
   *
   * @var object
   */
  protected $stream;

  /**
   * Builds the edit form.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param string|null $streamuri
   *   Base64-encoded URI of the Stream to edit.
   *
   * @return array
   *   The built form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $streamuri = NULL) {
    // Disable HTML5 native validation.
    $form['#attributes']['novalidate'] = 'novalidate';

    // Attach libraries for tabs and AJAX states.
    $form['#attached']['library'][] = 'dpl/dpl_onlytabs';
    $form['#attached']['library'][] = 'core/drupal.states';
    $form['#attached']['library'][] = 'core/jquery.once';

    // 1) Load the existing Stream via API.
    $api = \Drupal::service('rep.api_connector');
    $decodedUri = base64_decode($streamuri);
    $response = json_decode($api->getUri($decodedUri));
    if (empty($response->isSuccessful)) {
      \Drupal::messenger()->addError($this->t('Failed to retrieve Stream.'));
      $this->backUrl();
      return [];
    }
    $this->stream = $response->body;

    // === Initialize topics: only on first build, load from Stream; afterward, use form_state ===
    if ($form_state->has('topics')) {
      // On AJAX rebuilds: keep whatever is in form_state.
      $topics = $form_state->get('topics');
    }
    else {
      // First page load: pull from the API Stream object.
      $topics = [];
      if (!empty($this->stream->topics) && is_array($this->stream->topics)) {
        foreach ($this->stream->topics as $item) {
          $dplObj = !empty($item->deploymentUri)
            ? $api->parseObjectResponse($api->getUri($item->deploymentUri), 'getUri')
            : NULL;
          $sddObj = !empty($item->semanticDataDictionaryUri)
            ? $api->parseObjectResponse($api->getUri($item->semanticDataDictionaryUri), 'getUri')
            : NULL;
          $topics[] = [
            'topic'      => $item->label ?? '',
            'deployment' => $dplObj
              ? Utils::trimAutoCompleteString($dplObj->label, $dplObj->uri)
              : '',
            'sdd'        => $sddObj
              ? Utils::trimAutoCompleteString($sddObj->label, $sddObj->uri)
              : '',
            'cellscope'  => is_array($item->cellScopeUri)
              ? ($item->cellScopeUri[0] ?? '')
              : ($item->cellScopeUri ?? ''),
          ];
        }
      }
      // Save into form_state so AJAX keeps appending to it.
      $form_state->set('topics', $topics);
    }

    // 3) Prepare default for deployment autocomplete.
    $deploymentLabel = '';
    if (!empty($this->stream->deployment->uri) && !empty($this->stream->deployment->label)) {
      $deploymentLabel = Utils::trimAutoCompleteString(
        $this->stream->deployment->label,
        $this->stream->deployment->uri
      );
    }

    // 4) Determine selected method (from rebuild or loaded Stream).
    $method = $form_state->getValue('stream_method', $this->stream->method);
    $form_state->set('selected_method', $method);

    // 5) AJAX wrapper for tabs.
    $form['tabs'] = [
      '#type'   => 'container',
      '#prefix' => '<div id="method-properties-wrapper">',
      '#suffix' => '</div>',
      '#attributes' => ['class' => ['tabs']],
    ];

    // 6) Tab navigation links.
    $form['tabs']['tab_links'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['nav', 'nav-tabs']],
    ];
    // Basic Properties link (always shown).
    $form['tabs']['tab_links']['basic'] = [
      '#type' => 'html_tag',
      '#tag' => 'li',
      '#attributes' => ['class' => ['nav-item']],
      '#value' => '<a class="nav-link active" data-toggle="tab" href="#edit-tab1">'
        . $this->t('Basic Properties') . '</a>',
    ];
    // File-Method link.
    $form['tabs']['tab_links']['file'] = [
      '#type'   => 'html_tag',
      '#tag'    => 'li',
      '#access' => ($method === 'files'),
      '#attributes' => ['class' => ['nav-item']],
      '#value' => '<a class="nav-link" data-toggle="tab" href="#edit-tab2">'
        . $this->t('File-Method Properties') . '</a>',
    ];
    // Message-Method link.
    $form['tabs']['tab_links']['message'] = [
      '#type'   => 'html_tag',
      '#tag'    => 'li',
      '#access' => ($method === 'messages'),
      '#attributes' => ['class' => ['nav-item']],
      '#value' => '<a class="nav-link" data-toggle="tab" href="#edit-tab3">'
        . $this->t('Message-Method Properties') . '</a>',
    ];

    // 7) Tab contents container.
    $form['tabs']['tab_content'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tab-content']],
    ];

    //
    // === TAB 1: Basic Properties ===
    //
    $form['tabs']['tab_content']['tab1'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['tab-pane', 'active', 'p-3', 'border', 'border-light'],
        'id'    => 'edit-tab1',
      ],
    ];
    // Method selector with AJAX callback.
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
    // Permission selector.
    $form['tabs']['tab_content']['tab1']['permission_uri'] = [
      '#type' => 'select',
      '#title' => $this->t('Permission'),
      '#options' => [
        HASCO::PUBLIC  => $this->t('Public'),
        HASCO::PRIVATE => $this->t('Private'),
      ],
      '#default_value' => $this->stream->permissionUri ?? HASCO::PUBLIC,
      '#required' => TRUE,
    ];
    // Study autocomplete field.
    $form['tabs']['tab_content']['tab1']['stream_study'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Study'),
      '#autocomplete_route_name' => 'std.study_autocomplete',
      '#default_value' => Utils::fieldToAutocomplete(
        $this->stream->studyUri,
        $this->stream->study->label
      ),
    ];
    // Version (readonly).
    $form['tabs']['tab_content']['tab1']['stream_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $this->stream->hasVersion,
      '#disabled' => TRUE,
    ];
    // Description textarea.
    $form['tabs']['tab_content']['tab1']['stream_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->stream->comment,
    ];

    //
    // === TAB 2: File-Method Properties ===
    //
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
      '#default_value' => $this->stream->datasetPattern ?? '',
      '#required' => ($method === 'files'),
    ];
    // Deployment field.
    $form['tabs']['tab_content']['tab2']['stream_deployment'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Deployment'),
      '#autocomplete_route_name' => 'std.deployment_autocomplete',
      '#default_value' => $deploymentLabel,
      '#required' => TRUE,
    ];
    // Semantic Data Dictionary autocomplete.
    $form['tabs']['tab_content']['tab2']['stream_semanticdatadictionary'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Semantic Data Dictionary'),
      '#autocomplete_route_name' => 'std.semanticdatadictionary_autocomplete',
      '#default_value' => Utils::fieldToAutocomplete(
        $this->stream->semanticDataDictionaryUri,
        $this->stream->semanticDataDictionary->label
      ),
    ];
    // Cell Scope URI field.
    $form['tabs']['tab_content']['tab2']['stream_cell_scope_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cell Scope URI'),
      '#default_value' => is_array($this->stream->cellScopeUri)
        ? ($this->stream->cellScopeUri[0] ?? '')
        : ($this->stream->cellScopeUri ?? ''),
    ];
    // Cell Scope Name textarea.
    $form['tabs']['tab_content']['tab2']['stream_cell_scope_name'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Cell Scope Name'),
      '#default_value' => is_array($this->stream->cellScopeName)
        ? ($this->stream->cellScopeName[0] ?? '')
        : ($this->stream->cellScopeName ?? ''),
    ];

    //
    // === TAB 3: Message-Method Properties ===
    //
    $form['tabs']['tab_content']['tab3'] = [
      '#type'   => 'container',
      '#access' => ($method === 'messages'),
      '#attributes' => [
        'class' => ['tab-pane', 'p-3', 'border', 'border-light'],
        'id'    => 'edit-tab3',
      ],
    ];
    // Protocol selector.
    $form['tabs']['tab_content']['tab3']['stream_protocol'] = [
      '#type' => 'select',
      '#title' => $this->t('Protocol'),
      '#options' => ['MQTT' => 'MQTT', 'HTML' => 'HTML', 'ROS' => 'ROS'],
      '#default_value' => $this->stream->messageProtocol,
      '#required' => TRUE,
    ];
    // IP, Port, Archive ID fields.
    $fields_map = [
      'stream_ip'         => 'messageIP',
      'stream_port'       => 'messagePort',
      'stream_archive_id' => 'messageArchiveId',
    ];
    foreach ($fields_map as $field_name => $property) {
      $form['tabs']['tab_content']['tab3'][$field_name] = [
        '#type' => 'textfield',
        '#title' => $this->t(ucfirst(str_replace('message', '', $property))),
        '#default_value' => $this->stream->{$property} ?? '',
        '#required' => TRUE,
      ];
    }

    // === Topics subsection (messages only) ===
    $form['tabs']['tab_content']['tab3']['topics_title'] = [
      '#type'   => 'markup',
      '#markup' => '<h4>' . $this->t('Topics') . '</h4>',
      '#access' => ($method === 'messages'),
    ];

    $form['tabs']['tab_content']['tab3']['topics'] = [
      '#type' => 'container',
      '#access' => ($method === 'messages'),
      // This wrapper ID must match the AJAX callback wrapper.
      '#attributes' => [
        'id'    => 'topics-ajax-wrapper',
        'class' => ['row', 'p-3', 'bg-light', 'border', 'rounded'],
      ],
    ];

    // 1) Header row
    $form['tabs']['tab_content']['tab3']['topics']['header'] = [
      '#type' => 'markup',
      '#markup' =>
        '<div class="col bg-secondary text-white p-2 border border-white">' . $this->t('Topic Name') . '</div>' .
        '<div class="col bg-secondary text-white p-2 border border-white">' . $this->t('Deployment') . '</div>' .
        '<div class="col bg-secondary text-white p-2 border border-white">' . $this->t('Semantic Data Dictionary') . '</div>' .
        '<div class="col bg-secondary text-white p-2 border border-white">' . $this->t('Cell Scope') . '</div>' .
        '<div class="col-md-1 bg-secondary text-white p-2 border border-white">' . $this->t('Operations') . '</div>' .
        '<div class="w-100"></div>',
    ];

    // 2) Data rows (rendered by our helper)
    $form['tabs']['tab_content']['tab3']['topics']['rows'] = $this->renderTopicRows($form_state->get('topics'));

    // 3) “New Topic” button at bottom
    $form['tabs']['tab_content']['tab3']['topics']['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['col-12', 'mb-3']],
    ];
    $form['tabs']['tab_content']['tab3']['topics']['actions']['add_row'] = [
      '#type' => 'submit',
      '#value' => $this->t('New Topic'),
      '#name'  => 'new_topic',
      '#limit_validation_errors' => [],
      '#ajax'  => [
        'callback' => '::ajaxAddTopicCallback',
        'wrapper'  => 'topics-ajax-wrapper',
        'effect'   => 'fade',
      ],
      '#submit' => ['::submitAjaxAddTopic'],
    ];
    //
    // === Action Buttons: Save & Cancel ===
    //
    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#attributes' => ['class' => ['btn', 'btn-primary']],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#name' => 'back',
      '#limit_validation_errors' => [],
      // '#submit' => ['::backUrl'],
      '#attributes' => ['class' => ['btn', 'btn-secondary']],
    ];

    $form['space'] = [
      '#type' => 'item',
      '#value' => $this->t('<br><br><br>'),
      '#wrapper_attributes' => [
        'class' => ['mb-5']
      ]
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Validates required fields based on the selected method.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $method = $form_state->getValue('stream_method');
    if ($method === 'files') {
      if (empty($form_state->getValue('stream_datafile_pattern'))) {
        $form_state->setErrorByName('stream_datafile_pattern', $this->t('Datafile Pattern is required for Files method.'));
      }
      if (empty($form_state->getValue('stream_deployment'))) {
        $form_state->setErrorByName('stream_deployment', $this->t('Deployment is required for Files method.'));
      }
    }
    elseif ($method === 'messages') {
      foreach (['stream_protocol','stream_ip','stream_port','stream_archive_id'] as $field) {
        if (empty($form_state->getValue($field))) {
          $label = $form[$field]['#title'] ?? $field;
          $form_state->setErrorByName($field, $this->t('@label is required for Messages method.', ['@label' => $label]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * Handles form submission: updates Stream and Topics via the API.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Redirect if Cancel button was clicked.
    $trigger = $form_state->getTriggeringElement()['#name'];
    if ($trigger === 'back') {
      $this->backUrl();
      return;
    }

    $api = \Drupal::service('rep.api_connector');
    $email = \Drupal::currentUser()->getEmail();

    // Merge user input back into topics array.
    $topics = $form_state->get('topics') ?: [];
    $input = $form_state->getUserInput();
    foreach ($topics as $delta => &$item) {
      $item['topic']      = $input['topic_topic_' . $delta]      ?? $item['topic'];
      $item['deployment'] = $input['topic_deployment_' . $delta] ?? $item['deployment'];
      $item['sdd']        = $input['topic_sdd_' . $delta]        ?? $item['sdd'];
      $item['cellscope']  = $input['topic_cellscope_' . $delta]  ?? $item['cellscope'];
    }
    unset($item);

    // Build payload for Stream update.
    $payload = [
      'uri'                       => $this->stream->uri,
      'typeUri'                   => HASCO::STREAM,
      'hascoTypeUri'              => HASCO::STREAM,
      'label'                     => 'Stream',
      'method'                    => $form_state->getValue('stream_method'),
      'permissionUri'             => $form_state->getValue('permission_uri'),
      'studyUri'                  => Utils::uriFromAutocomplete($form_state->getValue('stream_study')),
      'hasVersion'                => $form_state->getValue('stream_version') ?? $this->stream->hasVersion,
      'comment'                   => $form_state->getValue('stream_description'),
      'canUpdate'                 => [$email],
      'designedAt'                => $this->stream->designedAt,
      'hasSIRManagerEmail'        => $email,
      'hasStreamStatus'           => $this->stream->hasStreamStatus,
    ];
    if ($payload['method'] === 'files') {
      $payload['datasetPattern']   = $form_state->getValue('stream_datafile_pattern');
      $payload['deploymentUri']    = Utils::uriFromAutocomplete($form_state->getValue('stream_deployment'));
      $payload['semanticDataDictionaryUri'] = Utils::uriFromAutocomplete($form_state->getValue('stream_semanticdatadictionary'));
      $payload['cellScopeUri']     = [$form_state->getValue('stream_cell_scope_uri')];
      $payload['cellScopeName']    = [$form_state->getValue('stream_cell_scope_name')];
      // Clear message fields.
      $payload['messageProtocol']  = '';
      $payload['messageIP']        = '';
      $payload['messagePort']      = '';
      $payload['messageArchiveId'] = '';
    }
    else {
      $payload['messageProtocol']   = $form_state->getValue('stream_protocol');
      $payload['messageIP']         = $form_state->getValue('stream_ip');
      $payload['messagePort']       = $form_state->getValue('stream_port');
      $payload['messageArchiveId']  = $form_state->getValue('stream_archive_id');
      // Clear file fields.
      $payload['datasetPattern']    = '';
      $payload['cellScopeUri']      = [];
      $payload['cellScopeName']     = [];
    }

    try {
      // Delete then re-create the Stream.
      $api->elementDel('stream', $this->stream->uri);
      $api->elementAdd('stream', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

      // Remove existing topics.
      if (!empty($this->stream->topics)) {
        foreach ($this->stream->topics as $old) {
          $api->elementDel('streamtopic', $old->uri);
        }
      }
      // Add updated topics.
      foreach ($topics as $item) {
        if (empty($item['topic'])) {
          continue;
        }
        $uriTopic = Utils::uriGen('streamtopic');
        $topicPayload = [
          'uri'                       => $uriTopic,
          'typeUri'                   => HASCO::STREAMTOPIC,
          'hascoTypeUri'              => HASCO::STREAMTOPIC,
          'streamUri'                 => $this->stream->uri,
          'label'                     => $item['topic'],
          'deploymentUri'             => Utils::uriFromAutocomplete($item['deployment']),
          'semanticDataDictionaryUri' => Utils::uriFromAutocomplete($item['sdd']),
          'cellScopeUri'              => [$item['cellscope']],
          'hasTopicStatus'            => HASCO::INACTIVE,
        ];
        $api->elementAdd('streamtopic', json_encode($topicPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
      }

      \Drupal::messenger()->addMessage($this->t('Stream has been updated successfully.'));
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError($this->t('Error updating Stream: @msg', ['@msg' => $e->getMessage()]));
    }

    // Redirect back.
    $this->backUrl();
  }

  /**
   * AJAX submit handler to add a new topic row.
   */
  public function submitAjaxAddTopic(array &$form, FormStateInterface $form_state) {
    $topics = $form_state->get('topics') ?: [];

    // Preserve existing input.
    $input = $form_state->getUserInput();
    foreach ($topics as $delta => &$item) {
      $item['topic']      = $input['topic_topic_' . $delta]      ?? $item['topic'];
      $item['deployment'] = $input['topic_deployment_' . $delta] ?? $item['deployment'];
      $item['sdd']        = $input['topic_sdd_' . $delta]        ?? $item['sdd'];
      $item['cellscope']  = $input['topic_cellscope_' . $delta]  ?? $item['cellscope'];
    }
    unset($item);

    // Append an empty topic row.
    $topics[] = ['topic' => '', 'deployment' => '', 'sdd' => '', 'cellscope' => ''];
    $form_state->set('topics', $topics);
    $form_state->setRebuild(TRUE);
  }

  /**
   * AJAX callback: rebuilds the topics container after adding a row.
   */
  public function ajaxAddTopicCallback(array &$form, FormStateInterface $form_state) {
    return $form['tabs']['tab_content']['tab3']['topics'];
  }

  /**
   * AJAX submit handler to remove a topic row.
   */
  public function submitAjaxRemoveTopic(array &$form, FormStateInterface $form_state) {
    $topics = $form_state->get('topics') ?: [];

    // Preserve existing input.
    $input = $form_state->getUserInput();
    foreach ($topics as $delta => &$item) {
      $item['topic']      = $input['topic_topic_' . $delta]      ?? $item['topic'];
      $item['deployment'] = $input['topic_deployment_' . $delta] ?? $item['deployment'];
      $item['sdd']        = $input['topic_sdd_' . $delta]        ?? $item['sdd'];
      $item['cellscope']  = $input['topic_cellscope_' . $delta]  ?? $item['cellscope'];
    }
    unset($item);

    // Determine which index to remove.
    $trigger = $form_state->getTriggeringElement();
    $parts = explode('_', $trigger['#name']);
    $index = (int) end($parts);
        if (isset($topics[$index])) {
          unset($topics[$index]);
          // Re-index the array to keep deltas sequential.
          $topics = array_values($topics);
        }
        // Save updated topics and rebuild form.
        $form_state->set('topics', $topics);
        $form_state->setRebuild(TRUE);
    }

    /**
     * AJAX callback: rebuilds the topics container after a row removal.
     */
    public function ajaxRemoveTopicCallback(array &$form, FormStateInterface $form_state) {
      return $form['tabs']['tab_content']['tab3']['topics'];
    }

    /**
     * AJAX callback to rebuild the tabs container when method changes.
     */
    public function updateMethodProperties(array &$form, FormStateInterface $form_state) {
      return $form['tabs'];
    }

    /**
     * Redirect helper to return to the previous page.
     */
    protected function backUrl() {
      $uid = \Drupal::currentUser()->id();
      $previous = Utils::trackingGetPreviousUrl($uid, 'dpl.edit_stream');
      if ($previous) {
        (new RedirectResponse($previous))->send();
      }
    }

    /**
     * Renders each topic row in the Topics section.
     *
     * @param array $topics
     *   Array of topic items from form_state.
     *
     * @return array
     *   Render array of topic row elements.
     */
    /**
     * Render each Topic row in the Topics container.
     *
     * @param array $topics
     *   Array of ['topic'=>'…','deployment'=>'…','sdd'=>'…','cellscope'=>'…'].
     *
     * @return array
     *   A renderable array of rows.
     */
    protected function renderTopicRows(array $topics) {
      $rows = [];
      // Separator to force line‐break after each row.
      $separator = '<div class="w-100"></div>';

      foreach ($topics as $delta => $item) {
        // Each row is a series of column wrappers…
        $row = [];

        // Topic Name column
        $row['topic'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['col', 'p-2', 'border', 'border-light']],
          'input' => [
            '#type'  => 'textfield',
            '#name'  => "topic_topic_$delta",
            '#value' => $item['topic'],
            '#attributes' => ['class' => ['form-control']],
          ],
        ];

        // Deployment column
        $row['deployment'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['col', 'p-2', 'border', 'border-light']],
          'input' => [
            '#type' => 'textfield',
            '#name' => "topic_deployment_$delta",
            '#value' => $item['deployment'],
            '#autocomplete_route_name' => 'std.deployment_autocomplete',
            '#attributes' => ['class' => ['form-control']],
          ],
        ];

        // Semantic Data Dictionary column
        $row['sdd'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['col', 'p-2', 'border', 'border-light']],
          'input' => [
            '#type' => 'textfield',
            '#name' => "topic_sdd_$delta",
            '#value' => $item['sdd'],
            '#autocomplete_route_name' => 'std.semanticdatadictionary_autocomplete',
            '#attributes' => ['class' => ['form-control']],
          ],
        ];

        // Cell Scope column
        $row['cellscope'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['col', 'p-2', 'border', 'border-light']],
          'input' => [
            '#type'  => 'textfield',
            '#name'  => "topic_cellscope_$delta",
            '#value' => $item['cellscope'],
            '#attributes' => ['class' => ['form-control']],
          ],
        ];

        // Operations column (Remove button)
        $row['operations'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-md-1', 'p-2', 'border', 'border-light']],
          'button' => [
            '#type' => 'submit',
            '#value' => $this->t('Remove'),
            '#name' => "topic_remove_$delta",
            '#limit_validation_errors' => [],
            '#attributes' => ['class' => ['btn', 'btn-sm', 'btn-danger']],
            '#ajax' => [
              'callback' => '::ajaxRemoveTopicCallback',
              'wrapper'  => 'topics-ajax-wrapper',
              'effect'   => 'fade',
            ],
            '#submit' => ['::submitAjaxRemoveTopic'],
          ],
        ];

        // Add the line‐break after this row
        $row['separator'] = [
          '#type' => 'markup',
          '#markup' => $separator,
        ];

        // Wrap the entire set under a unique key.
        $rows['row' . $delta] = $row;
      }

      return $rows;
    }


} // end of class
