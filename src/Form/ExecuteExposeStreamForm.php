<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\rep\Vocabulary\HASCO;
use Drupal\rep\Entity\Stream;
use Drupal\rep\Vocabulary\REPGUI;
use Drupal\Component\Render\Markup;

class ExecuteExposeStreamForm extends FormBase {

  protected $mode;

  protected $streamUri;

  protected $stream;

  protected $deployment;

  protected $deploymentUri;

  public function getMode() {
    return $this->mode;
  }
  public function setMode($mode) {
    return $this->mode = $mode;
  }

  public function getStream() {
    return $this->stream;
  }
  public function setStream($stream) {
    return $this->stream = $stream;
  }

  public function getStreamUri() {
    return $this->streamUri;
  }
  public function setStreamUri($uri) {
    return $this->streamUri = $uri;
  }

  public function getDeployment() {
    return $this->deployment;
  }
  public function setDeployment($deployment) {
    return $this->deployment = $deployment;
  }

  public function getDeploymentUri() {
    return $this->deploymentUri;
  }
  public function setDeploymentUri($deploymentUri) {
    return $this->deploymentUri = $deploymentUri;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'execute_expose_stream_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $mode = NULL, $streamuri = NULL) {

    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();

    // Globals
    $api = \Drupal::service('rep.api_connector');

    if (($mode == NULL) ||
        ($mode != 'expose')) {
      \Drupal::messenger()->addError(t("Invalid Stream expose operation."));
      self::backUrl();
      return;
    }
    $this->setMode($mode);

    // GET STREAM FROM API
    $uri=$streamuri;
    $uri_decode=base64_decode($uri);
    $this->setStreamUri($uri_decode);

    $rawresponse = $api->getUri($this->getStreamUri());
    $obj = json_decode($rawresponse);

    if ($obj->isSuccessful) {
      $this->setStream($obj->body);
      $this->setDeployment($this->getStream()->deployment);
      $this->setDeploymentUri($this->getStream()->deployment->uri);
    } else {
      \Drupal::messenger()->addError(t("Failed to retrieve Stream."));
      self::backUrl();
      return;
    }

    $studyLabel = ' ';
    if (isset($this->getStream()->study) &&
        isset($this->getStream()->study->uri) &&
        isset($this->getStream()->study->label)) {
      $studyLabel = Utils::fieldToAutocomplete(
        $this->getStream()->study->uri,
        $this->getStream()->study->label
      );
    }
    $sddLabel = ' ';
    if (isset($this->getStream()->semanticDataDictionary) &&
        isset($this->getStream()->semanticDataDictionary->uri) &&
        isset($this->getStream()->semanticDataDictionary->label)) {
      $sddLabel = Utils::fieldToAutocomplete(
        $this->getStream()->semanticDataDictionary->uri,
        $this->getStream()->semanticDataDictionary->label
      );
    }

    $validationError = NULL;

    if ($this->getStream()->method === 'Files') {
      if (!isset($this->getStream()->study) && !isset($this->getStream()->semanticDataDictionary)) {
        $validationError = "Stream is missing both STUDY and SEMANTIC DATA DICTIONARY.";
      }
      if (!isset($this->getStream()->study) && isset($this->getStream()->semanticDataDictionary)) {
        $validationError = "Stream is missing associated STUDY.";
      }
      if (isset($this->getStream()->study) && !isset($this->getStream()->semanticDataDictionary)) {
        $validationError = "Stream is missing associated SEMANTIC DATA DICTIONARY.";
      }
    } else {
      if (!isset($this->getStream()->study) ) {
        $validationError = "Stream is missing STUDY.";
      }
    }

    if ($this->getMode() == 'expose') {
      $form['page_title'] = [
        '#type' => 'item',
        '#title' => $this->t('<h3>Expose Stream</h3>'),
      ];
    }
    if ($this->getMode() == 'close') {
      $form['page_title'] = [
        '#type' => 'item',
        '#title' => $this->t('<h3>Close Stream Exposure</h3>'),
      ];
    }

    $uri_string = Utils::namespaceUri($this->getStream()->uri);
    $href = $root_url . REPGUI::DESCRIBE_PAGE . base64_encode($uri_string);

    $link_html = '<a target="_new" href="' . $href . '" target="_blank">' . $uri_string . '</a>';

    $form['stream_uri'] = [
      '#type'   => 'markup',
      '#markup' => t('<div class="mb-3"><strong>URI:</strong> '.$link_html.'</div>'),
    ];

    $form['stream_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getStream()->label,
      '#disabled' => TRUE,
    ];
    $form['stream_platform_instance'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Study'),
      '#default_value' => $studyLabel,
      '#disabled' => TRUE,
    ];
    if ($this->getStream()->method === 'Files') {
      $form['stream_semanticDataDictionary_instance'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Semantic Data Dictionary (SDD)'),
        '#default_value' => $sddLabel,
        '#disabled' => TRUE,
      ];
    }

    // DEPLOYMENT IS VALID
    if ($validationError == NULL) {

      if ($this->getMode() == 'expose') {

        $form['stream_exposeip'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Stream IP'),
          '#default_value' => $this->getStream()->ipCidr ?? '',
          '#description' => $this->t('Insert one valid IPv4, example 192.168.0.1'),
          '#required' => false,
          '#attributes' => [
            'placeholder' => 'xxx.xxx.xxx.xxx',
            'pattern'     => '^(?:(?:25[0-5]|2[0-4]\d|[01]?\d?\d)\.){3}(?:25[0-5]|2[0-4]\d|[01]?\d?\d)$',
          ],
        ];

        // Add the password field wrapped in a Bootstrap input-group
        $form['stream_exposepassword'] = [
          '#type'        => 'textfield',
          '#title'       => $this->t('Stream Password'),
          '#description' => $this->t('Type in the autorizating password for the stream'),
          '#attributes'  => [
            'class'        => ['col-md-2'],
            'style'       => 'width: 250px;',
            'autocomplete' => 'off',
          ],
        ];

        $form['topic_table_title'] = [
            '#type' => 'item',
            '#title' => $this->t('<h3>Stream Topics List</h3>'),
        ];

        $header = Stream::generateHeaderTopic(true);
        $output = Stream::generateOutputTopic($this->getStream()->topics, $this->getStream()->uri, 'select', true);
        $form['element_table'] = [
          '#type' => 'table',
          '#header' => $header,
          '#rows' => $output,
          '#js_select' => FALSE,
          '#empty' => t('No stream topic has been found'),
        ];
        // $form['card']['card_body']['pager'] = [
        //   '#theme' => 'list-page',
        //   '#items' => [
        //     'page' => strval($page),
        //     'first' => ListStreamStatePage::link($apiState, $this->getManagerEmail(), 1, $pagesize),
        //     'last' => ListStreamStatePage::link($apiState, $this->getManagerEmail(), $total_pages, $pagesize),
        //     'previous' => $previous_page_link,
        //     'next' => $next_page_link,
        //     'last_page' => strval($total_pages),
        //     'links' => null,
        //     'title' => ' ',
        //   ],
        // ];
        $form['space1'] = [
          '#type' => 'item',
          '#value' => $this->t('<br><br>'),
        ];

        $form['expose_submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Expose'),
          '#name' => 'expose',
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'expose-button'],
          ],
        ];
      }

      if ($this->getMode() == 'close') {
        $form['stream_end_datetime'] = [
          '#type' => 'datetime',
          '#title' => $this->t('Ending Date/Time'),
          '#default_value' => $this->getStream()->endedAt ? $this->getStream()->endedAt : '',
          '#date_date_element' => 'date', // Use a date element
          '#date_time_element' => 'time', // Use a time element
          '#date_format' => 'Y-m-d', // Date format
          '#time_format' => 'H:i:s', // Time format
        ];
        $form['close_submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Close'),
          '#name' => 'close',
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'close-button'],
          ],
        ];
      }

      $form['cancel_submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Cancel'),
        '#name' => 'back',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'cancel-button'],
        ],
      ];

      // $form[$this->getFormId()] = [
      //   '#attributes' => [
      //     'class' => ['col-md-4'],
      //   ],
      // ]

    // DEPLOYMENT IS INVALID
    } else {
      $form['validation_notification'] = [
        '#type' => 'item',
        '#title' => $this->t('<br><ul><h2>Stream cannot be exposed</h2></ul>'),
      ];
      $form['validation_reason'] = [
        '#type' => 'item',
        '#title' => $this->t('<ul><b>Reason: ' . $validationError . '</b></ul><br>'),
      ];
      $form['cancel_submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Back to Manage Streams'),
        '#name' => 'back',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'back-button'],
        ],
      ];
    }
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
      if(strlen($form_state->getValue('stream_name')) < 1) {
        $form_state->setErrorByName('stream_name', $this->t('Please enter a valid name'));
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

    try{
      $uid = \Drupal::currentUser()->id();
      $useremail = \Drupal::currentUser()->getEmail();

      $orig = $this->getStream();

      // TODO WE MUST EXPOSE THE STREAM

      $api = \Drupal::service('rep.api_connector');
      $api->elementDel('stream', $this->getStreamUri());
      $api->elementAdd('stream', json_encode([]));

      \Drupal::messenger()->addMessage(t("Stream has been exposed successfully."));
      self::backUrl();
      return;

    } catch(\Exception $e) {
      \Drupal::messenger()->addError(t("An error occurred while exposing the Stream: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  function backUrl() {
    $route_name = 'dpl.manage_streams_route';
    $route_params = [
      'deploymenturi' => base64_encode($this->getDeployment()->uri),
      'state'         => 'active',
      'page'          => '1',
      'pagesize'      => '10',
    ];
    // cria a URL de rota já com parâmetros e converte em string
    $url = Url::fromRoute($route_name, $route_params)->toString();

    $response = new RedirectResponse($url);
    $response->send();

    return;
  }
}
