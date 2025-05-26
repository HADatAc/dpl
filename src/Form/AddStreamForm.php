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
 * - File‐Method Properties (only when method = 'files')
 * - Message‐Method Properties (only when method = 'messages')
 * Uses server-side #access to conditionally render tabs.
 */
class AddStreamForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_stream_form';
  }

  /**
   * Deployment object retrieved from API.
   *
   * @var object
   */
  protected $deployment;

  /**
   * Getter for deployment.
   *
   * @return object
   */
  public function getDeployment() {
    return $this->deployment;
  }

  /**
   * Setter for deployment.
   *
   * @param object $deployment
   *   Deployment data returned from the API.
   */
  public function setDeployment($deployment) {
    $this->deployment = $deployment;
  }

  /**
   * {@inheritdoc}
   *
   * Build the form with three tabs:
   * - Basic Properties: always visible
   * - File-Method Properties: visible when method = 'files'
   * - Message-Method Properties: visible when method = 'messages'
   *
   * Uses #ajax to rebuild on method change and #access to include/exclude tabs.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $deploymenturi = NULL) {
    // Attach libraries for custom tabs and Drupal States.
    $form['#attached']['library'][] = 'dpl/dpl_onlytabs';
    $form['#attached']['library'][] = 'core/drupal.states';
    $form['#attached']['library'][] = 'core/jquery.once';

    // Load deployment via API.
    $api = \Drupal::service('rep.api_connector');
    $decoded = base64_decode($deploymenturi);
    $response = json_decode($api->getUri($decoded));
    if (empty($response->isSuccessful)) {
      \Drupal::messenger()->addError($this->t('Failed to retrieve Deployment.'));
      $this->backUrl();
      return [];
    }
    $this->setDeployment($response->body);

    // Prepare deployment autocomplete label.
    $deploymentLabel = '';
    if (isset($this->deployment->uri, $this->deployment->label)) {
      $deploymentLabel = Utils::fieldToAutocomplete(
        $this->deployment->uri,
        $this->deployment->label
      );
    }

    // Determine selected method or default to 'files'.
    $method = $form_state->getValue('stream_method', 'files');

    // AJAX wrapper for tabs.
    $form['tabs'] = [
      '#type' => 'container',
      '#prefix' => '<div id="method-properties-wrapper">',
      '#suffix' => '</div>',
      '#attributes' => ['class' => ['tabs']],
    ];

    // Build tab links.
    $form['tabs']['tab_links'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['nav', 'nav-tabs']],
    ];

    // Tab 1: Basic Properties link (always rendered).
    $form['tabs']['tab_links']['basic'] = [
      '#type' => 'html_tag',
      '#tag' => 'li',
      '#attributes' => ['class' => ['nav-item']],
      '#value' => '<a class="nav-link active" data-toggle="tab" href="#edit-tab1">'
        . $this->t('Basic Properties') .
        '</a>',
    ];

    // Tab 2: File-Method Properties link (render only if method = files).
    $form['tabs']['tab_links']['file'] = [
      '#type'   => 'html_tag',
      '#tag'    => 'li',
      '#access' => ($method === 'files'),
      '#attributes' => ['class' => ['nav-item']],
      '#value' => '<a class="nav-link" data-toggle="tab" href="#edit-tab2">'
        . $this->t('File-Method Properties') .
        '</a>',
    ];

    // Tab 3: Message-Method Properties link (render only if method = messages).
    $form['tabs']['tab_links']['message'] = [
      '#type'   => 'html_tag',
      '#tag'    => 'li',
      '#access' => ($method === 'messages'),
      '#attributes' => ['class' => ['nav-item']],
      '#value' => '<a class="nav-link" data-toggle="tab" href="#edit-tab3">'
        . $this->t('Message-Method Properties') .
        '</a>',
    ];

    // Build tab content container.
    $form['tabs']['tab_content'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tab-content']],
    ];

    //
    // TAB 1 CONTENT: Basic Properties (always rendered and always active).
    //
    $form['tabs']['tab_content']['tab1'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['tab-pane', 'active', 'p-3', 'border', 'border-light'],
        'id'    => 'edit-tab1',
      ],
    ];
    // Deployment field (autocomplete, disabled).
    $form['tabs']['tab_content']['tab1']['stream_deployment'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Deployment'),
      '#default_value' => $deploymentLabel,
      '#disabled' => TRUE,
      '#required' => TRUE,
    ];
    // Method select triggers AJAX rebuild.
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
    // Study and SDD autocompletes.
    $form['tabs']['tab_content']['tab1']['stream_study'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Study'),
      '#autocomplete_route_name' => 'std.study_autocomplete',
    ];
    $form['tabs']['tab_content']['tab1']['stream_semanticdatadictionary'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SDD'),
      '#autocomplete_route_name' => 'std.semanticdatadictionary_autocomplete',
    ];
    // Version (fixed) and Description.
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
    // TAB 2 CONTENT: File-Method Properties (render only if method = files).
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
    // Cell Scope URI.
    $form['tabs']['tab_content']['tab2']['stream_cell_scope_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cell Scope URI'),
      '#required' => ($method === 'files'),
    ];
    // Cell Scope Name.
    $form['tabs']['tab_content']['tab2']['stream_cell_scope_name'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Cell Scope Name'),
      '#required' => ($method === 'files'),
    ];

    //
    // TAB 3 CONTENT: Message-Method Properties (render only if method = messages).
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
    // IP, Port, Header, Archive ID.
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

    // Header
    $form['tabs']['tab_content']['tab3']['stream_header'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Header'),
      // '#required' => ($method === 'messages'),
    ];

    // Action buttons.
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

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Validate required fields based on selected method.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $method = $form_state->getValue('stream_method');
    if ($method === 'files') {
      foreach ([
        'stream_datafile_pattern' => $this->t('Datafile Pattern'),
        'stream_cell_scope_uri'   => $this->t('Cell Scope URI'),
        'stream_cell_scope_name'  => $this->t('Cell Scope Name'),
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
        // 'stream_header'      => $this->t('Header'),
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
   * Submit handler: processes Save or Cancel.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement()['#name'];
    $method = $form_state->getValue('stream_method');
    if ($trigger === 'back') {
      $this->backUrl();
      return;
    }

    // Build payload.
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
      'method'                    => $form_state->getValue('stream_method'),
      'permissionUri'             => $form_state->getValue('permission_uri'),
      'deploymentUri'             => $deployment,
      'hasVersion'                => $form_state->getValue('stream_version') ?? 1,
      'comment'                   => $form_state->getValue('stream_description'),
      'canUpdate'                 => [$email],
      'designedAt'                => $timestamp,
      'studyUri'                  => Utils::uriFromAutocomplete($form_state->getValue('stream_study')),
      'semanticDataDictionaryUri' => Utils::uriFromAutocomplete($form_state->getValue('stream_semanticdatadictionary')),
      'hasSIRManagerEmail'        => $email,
      'hasStreamStatus'           => HASCO::DRAFT,
    ];

    if ($method === 'files') {
      $stream['datasetPattern'] = $form_state->getValue('stream_datafile_pattern');
      $stream['cellScopeUri']    = [$form_state->getValue('stream_cell_scope_uri')];
      $stream['cellScopeName']   = [$form_state->getValue('stream_cell_scope_name')];
      $stream['messageProtocol']  = '';
      $stream['messageIP']        = '';
      $stream['messagePort']      = '';
      $stream['messageArchiveId'] = '';
      // $stream['messageHeader']    = '';
    }
    else {
      $stream['messageProtocol']   = $form_state->getValue('stream_protocol');
      $stream['messageIP']         = $form_state->getValue('stream_ip');
      $stream['messagePort']       = $form_state->getValue('stream_port');
      $stream['messageArchiveId']  = $form_state->getValue('stream_archive_id');
      // $stream['messageHeader']     = $form_state->getValue('stream_header');
      $stream['datasetPattern']   = '';
      $stream['cellScopeUri']      = [];
      $stream['cellScopeName']     = [];
    }

    // dpm(json_encode($stream));return false;

    try {
      \Drupal::service('rep.api_connector')->elementAdd('stream', json_encode($stream));
      \Drupal::messenger()->addMessage($this->t('Stream has been added successfully.'));
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError($this->t('Error adding Stream: @msg', ['@msg' => $e->getMessage()]));
    }

    // Redirect back.
    $this->backUrl();
  }

  /**
   * Redirect helper to the manage_streams_route.
   */
  public function backUrl() {
    $params = [
      'deploymenturi' => base64_encode($this->deployment->uri),
      'state'         => 'design',
      'page'          => '1',
      'pagesize'      => '10',
    ];
    $url = Url::fromRoute('dpl.manage_streams_route', $params)->toString();
    (new RedirectResponse($url))->send();
  }

  /**
   * AJAX callback to rebuild the tabs container when method changes.
   */
  public function updateMethodProperties(array &$form, FormStateInterface $form_state) {
    return $form['tabs'];
  }

}
