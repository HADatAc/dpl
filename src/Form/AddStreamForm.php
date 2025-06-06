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
 * Adds dynamic “Topics” functionality in the Messages tab, mirroring EditStreamForm.
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
    // Anexa bibliotecas de tabs e estados.
    $form['#attached']['library'][] = 'dpl/dpl_onlytabs';
    $form['#attached']['library'][] = 'core/drupal.states';
    $form['#attached']['library'][] = 'core/jquery.once';

    // 1) Inicializa “topics” no form_state.
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

    // 2) Recupera “selected_method” salvo em form_state ou, se não houver,
    //    pega o valor vindo de getValue('stream_method'). Se nenhuma existir,
    //    usa 'files' como padrão.
    if ($form_state->hasValue('stream_method')) {
      $method = $form_state->getValue('stream_method');
    }
    elseif ($form_state->has('selected_method')) {
      $method = $form_state->get('selected_method');
    }
    else {
      $method = 'files';
    }
    // Salva de volta para o próximo rebuild.
    $form_state->set('selected_method', $method);

    // 3) Contêiner AJAX que envolve as três abas.
    $form['tabs'] = [
      '#type' => 'container',
      '#prefix' => '<div id="method-properties-wrapper">',
      '#suffix' => '</div>',
      '#attributes' => ['class' => ['tabs']],
    ];

    // 4) Links de navegação das abas.
    $form['tabs']['tab_links'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['nav', 'nav-tabs']],
    ];
    // Aba 1: Basic Properties (sempre visível).
    $form['tabs']['tab_links']['basic'] = [
      '#type' => 'html_tag',
      '#tag' => 'li',
      '#attributes' => ['class' => ['nav-item']],
      '#value' => '<a class="nav-link active" data-toggle="tab" href="#edit-tab1">'
        . $this->t('Basic Properties') .
        '</a>',
    ];
    // Aba 2: File-Method (só se $method === 'files').
    $form['tabs']['tab_links']['file'] = [
      '#type'   => 'html_tag',
      '#tag'    => 'li',
      '#access' => ($method === 'files'),
      '#attributes' => ['class' => ['nav-item']],
      '#value' => '<a class="nav-link" data-toggle="tab" href="#edit-tab2">'
        . $this->t('File-Method Properties') .
        '</a>',
    ];
    // Aba 3: Message-Method (só se $method === 'messages').
    $form['tabs']['tab_links']['message'] = [
      '#type'   => 'html_tag',
      '#tag'    => 'li',
      '#access' => ($method === 'messages'),
      '#attributes' => ['class' => ['nav-item']],
      '#value' => '<a class="nav-link" data-toggle="tab" href="#edit-tab3">'
        . $this->t('Message-Method Properties') .
        '</a>',
    ];

    // 5) Contêiner de conteúdo das abas.
    $form['tabs']['tab_content'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tab-content']],
    ];

    //
    // === ABA 1: Basic Properties ===
    //
    $form['tabs']['tab_content']['tab1'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['tab-pane', 'active', 'p-3', 'border', 'border-light'],
        'id'    => 'edit-tab1',
      ],
    ];
    // Select “Method” com AJAX para rebuild das abas.
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
      '#title' => $this->t('Study'),
      '#autocomplete_route_name' => 'std.study_autocomplete',
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

    //
    // === ABA 2: File-Method Properties ===
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

    //
    // === ABA 3: Message-Method Properties ===
    //
    $form['tabs']['tab_content']['tab3'] = [
      '#type'   => 'container',
      '#access' => ($method === 'messages'),
      '#attributes' => [
        'class' => ['tab-pane', 'p-3', 'border', 'border-light'],
        'id'    => 'edit-tab3',
      ],
    ];
    // Protocol select.
    $form['tabs']['tab_content']['tab3']['stream_protocol'] = [
      '#type' => 'select',
      '#title' => $this->t('Protocol'),
      '#options' => ['MQTT' => 'MQTT', 'HTML' => 'HTML', 'ROS' => 'ROS'],
      '#default_value' => 'MQTT',
      '#required' => ($method === 'messages'),
    ];
    // IP, Port, Archive ID.
    foreach ([
      'stream_ip'         => $this->t('IP'),
      'stream_port'       => $this->t('Port'),
      // 'stream_header'     => $this->t('Header'),
      'stream_archive_id' => $this->t('Archive ID'),
    ] as $field => $label) {
      $form['tabs']['tab_content']['tab3'][$field] = [
        '#type' => 'textfield',
        '#title' => $label,
        '#required' => ($method === 'messages'),
      ];
    }

    //
    // === SEÇÃO DE TOPICS (apenas se method = messages) ===
    //
    $form['tabs']['tab_content']['tab3']['topics_title'] = [
      '#type' => 'markup',
      '#markup' => '<h4 class="mt-4">' . $this->t('Topics') . '</h4>',
      '#access' => ($method === 'messages'),
    ];

    // Container que será substituído via AJAX.
    $form['tabs']['tab_content']['tab3']['topics'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'topics-ajax-wrapper',
        'class' => [
          'p-3', 'bg-light', 'text-dark', 'row',
          'border', 'border-secondary', 'rounded',
        ],
      ],
      '#access'   => ($method === 'messages'),
      '#attached' => [],  // Garante que não seja null no AJAX response.
    ];

    $separator = '<div class="w-100"></div>';

    // Cabeçalho da tabela de Topics.
    $form['tabs']['tab_content']['tab3']['topics']['header'] = [
      '#type' => 'markup',
      '#markup' =>
        '<div class="p-2 col bg-secondary text-white border border-white">' . $this->t('Topic Name') . '</div>' .
        '<div class="p-2 col bg-secondary text-white border border-white">' . $this->t('Deployment') . '</div>' .
        '<div class="p-2 col bg-secondary text-white border border-white">' . $this->t('Semantic Data Dictionary') . '</div>' .
        '<div class="p-2 col bg-secondary text-white border border-white">' . $this->t('Cell Scope') . '</div>' .
        '<div class="p-2 col-md-1 bg-secondary text-white border border-white">' . $this->t('Operations') . '</div>' .
        $separator,
    ];

    // Linhas existentes de “topics”.
    $form['tabs']['tab_content']['tab3']['topics']['rows'] = $this->renderTopicRows($topics);

    // Espaço extra.
    $form['tabs']['tab_content']['tab3']['topics']['space_3'] = [
      '#type' => 'markup',
      '#markup' => $separator,
    ];

    // Botão “New Topic” (AJAX).
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

    // “Header” extra (apenas em mensagens).
    $form['tabs']['tab_content']['tab3']['stream_header'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Header'),
      '#access' => ($method === 'messages'),
      '#wrapper_attributes' => [
        'class' => ['mt-3']
      ]
    ];

    //
    // === BOTÕES FINAIS: Save / Cancel ===
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

    // Persiste o array de topics para próximos rebuilds.
    $form_state->set('topics', $topics);

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Valida campos obrigatórios conforme método selecionado.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
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
      foreach ([
        'stream_protocol'    => $this->t('Protocol'),
        'stream_ip'          => $this->t('IP'),
        'stream_port'        => $this->t('Port'),
        'stream_archive_id'  => $this->t('Archive ID'),
      ] as $key => $label) {
        if (empty($form_state->getValue($key))) {
          $form_state->setErrorByName($key, $this->t('@label is mandatory for Messages method.', ['@label' => $label]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * Submit handler final: salva Stream ou cancela.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement()['#name'];
    $method = $form_state->getValue('stream_method');
    if ($trigger === 'back') {
      $this->backUrl();
      return;
    }

    // Recupera e atualiza valores de “topics” antes de montar o payload.
    $topics = $form_state->get('topics') ?: [];
    $input = $form_state->getUserInput();
    foreach ($topics as $delta => &$topicItem) {
      $topicItem['topic']      = $input['topic_topic_' . $delta]      ?? $topicItem['topic'];
      $topicItem['deployment'] = $input['topic_deployment_' . $delta] ?? $topicItem['deployment'];
      $topicItem['sdd']        = $input['topic_sdd_' . $delta]        ?? $topicItem['sdd'];
      $topicItem['cellscope']  = $input['topic_cellscope_' . $delta]  ?? $topicItem['cellscope'];
    }
    unset($topicItem);

    // Monta data/hora e demais campos.
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
      $stream['hasMessageStatus']  = HASCO::INACTIVE;

    }

    try {
      \Drupal::service('rep.api_connector')
        ->elementAdd('stream', json_encode($stream, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

      // Anexa “topics” só se existir ao menos um.
      if (!empty($topics)) {

        foreach ($topics as $topicItem) {

          $uriTopic = Utils::uriGen('streamtopic');
          $streamTopic = [
            'uri'                       => $uriTopic,
            'streamUri'                 => $uri,
            'deploymentUri'             => Utils::uriFromAutocomplete($topicItem['deployment']),
            'semanticDataDictionaryUri' => Utils::uriFromAutocomplete($topicItem['sdd']),
            'cellScopeUri'              => [$topicItem['cellscope']],
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

    // Redireciona de volta.
    $this->backUrl();
  }

  /**
   * AJAX callback para rebuild das abas quando “Method” muda.
   */
  public function updateMethodProperties(array &$form, FormStateInterface $form_state) {
    return $form['tabs'];
  }

  /******************************
   *
   *    TOPICS FUNCTIONS
   *
   ******************************/

  /**
   * Renders each Topic row no container de tópicos.
   *
   * @param array $topics
   *   Array de tópicos salvo em form_state.
   *
   * @return array
   *   Render array das linhas de tópicos.
   */
  protected function renderTopicRows(array $topics) {
    $form_rows = [];
    $separator = '<div class="w-100"></div>';

    foreach ($topics as $delta => $topic) {
      // dpm($topic);
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
   * AJAX submit handler para adicionar nova linha de topic.
   */
  public function submitAjaxAddTopic(array &$form, FormStateInterface $form_state) {
    $topics = $form_state->get('topics') ?: [];

    // Preserva valores atuais antes de adicionar nova linha.
    $input = $form_state->getUserInput();
    foreach ($topics as $delta => &$topicItem) {
      $topicItem['topic']      = $input['topic_topic_' . $delta]      ?? $topicItem['topic'];
      $topicItem['deployment'] = $input['topic_deployment_' . $delta] ?? $topicItem['deployment'];
      $topicItem['sdd']        = $input['topic_sdd_' . $delta]        ?? $topicItem['sdd'];
      $topicItem['cellscope']  = $input['topic_cellscope_' . $delta]  ?? $topicItem['cellscope'];
    }
    unset($topicItem);

    // Anexa uma linha vazia ao array.
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
   * AJAX callback: retorna o container “topics” (atualizado).
   */
  public function ajaxAddTopicCallback(array &$form, FormStateInterface $form_state) {
    $build = $form['tabs']['tab_content']['tab3']['topics'];
    if (!isset($build['#attached']) || !is_array($build['#attached'])) {
      $build['#attached'] = [];
    }
    return $build;
  }

  /**
   * AJAX submit handler para remover uma linha de topic.
   */
  public function submitAjaxRemoveTopic(array &$form, FormStateInterface $form_state) {
    $topics = $form_state->get('topics') ?: [];

    // Preserva valores, exceto o que será removido.
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
   * AJAX callback: retorna o container “topics” após remoção.
   */
  public function ajaxRemoveTopicCallback(array &$form, FormStateInterface $form_state) {
    $build = $form['tabs']['tab_content']['tab3']['topics'];
    if (!isset($build['#attached']) || !is_array($build['#attached'])) {
      $build['#attached'] = [];
    }
    return $build;
  }

  /**
   * Redirect helper para a rota manage_streams_route.
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
