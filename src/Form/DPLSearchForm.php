<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\rep\Vocabulary\HASCO;
use Drupal\rep\Vocabulary\VSTOI;

class DPLSearchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dpl_search_form';
  }

  protected $elementtype;

  protected $keyword;

  protected $page;

  protected $pagesize;

  public function getElementType() {
    return $this->elementtype;
  }

  public function setElementType($type) {
    return $this->elementtype = $type;
  }

  public function getKeyword() {
    return $this->keyword;
  }

  public function setKeyword($kw) {
    return $this->keyword = $kw;
  }

  public function getPage() {
    return $this->page;
  }

  public function setPage($pg) {
    return $this->page = $pg;
  }

  public function getPageSize() {
    return $this->pagesize;
  }

  public function setPageSize($pgsize) {
    return $this->pagesize = $pgsize;
  }

  public function iconSubmitForm(array &$form, FormStateInterface $form_state) {
  $clicked_button = $form_state->getTriggeringElement()['#name'];
  $form_state->setValue('search_element_type', $clicked_button);
  $form_state->setValue('search_keyword', '');
}

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'dpl/dpl_icons';


    // RETRIEVE PARAMETERS FROM HTML REQUEST
    $request = \Drupal::request();
    $pathInfo = $request->getPathInfo();
    $pathElements = (explode('/',$pathInfo));
    $this->setElementType('platform');
    $this->setKeyword('');
    $this->setPage(1);
    $this->setPageSize(12);

    // IT IS A CLASS ELEMENT if size of path elements is equal 5
    if (sizeof($pathElements) == 5) {

          // ELEMENT TYPE
          $this->setElementType($pathElements[4]);

    // IT IS AN INSTANCE ELEMENT if size of path elements is greate or equal 7
    } else if (sizeof($pathElements) >= 7) {

      // ELEMENT TYPE
      $this->setElementType($pathElements[3]);

      // KEYWORD
      if ($pathElements[4] == '_') {
        $this->setKeyword('');
      } else {
        $this->setKeyword($pathElements[4]);
      }

      // PAGE
      $this->setPage((int)$pathElements[5]);

      // PAGESIZE
      $this->setPageSize((int)$pathElements[6]);
    }

    $preferred_instrument = \Drupal::config('rep.settings')->get('preferred_instrument');
    $preferred_detector = \Drupal::config('rep.settings')->get('preferred_detector');

   $form['element_icons'] = [
  '#type' => 'container',
  '#attributes' => ['class' => ['element-icons-grid']],
];

$element_types = [
  'platform' => ['label' => 'Platform', 'image' => 'platform_placeholder.png'],
  'platforminstance' => ['label' => 'Platform Instances', 'image' => 'platform_instance_placeholder.png'],
  'instrumentinstance' => ['label' => 'Instrument Instances', 'image' => 'instrument_instance_placeholder.png'],
  'detectorinstance' => ['label' => 'Detector Instances', 'image' => 'detector_instance_placeholder.png'],
  'actuatorinstance' => ['label' => 'Actuator Instances', 'image' => 'actuator_instance_placeholder.png'],
  'deployment' => ['label' => 'Deployments', 'image' => 'deployment_placeholder.png'],
  'stream' => ['label' => 'Message Streams', 'image' => 'message_stream_placeholder.svg'],
  'stream2' => ['label' => 'File Streams', 'image' => 'datafile_stream_placeholder.svg'],
];

foreach ($element_types as $type => $info) {
  $placeholder_image = '../modules/custom/rep/images/placeholders/' . $info['image'];

  $form['element_icons'][$type] = [
    '#type' => 'submit',
    '#value' => '',
    '#attributes' => [
      'class' => ['element-icon-button'],
      'style' => "background-image: url('$placeholder_image');",
      'title' => $this->t($info['label']),
      'aria-label' => $this->t($info['label']),
    ],
    '#name' => $type,
    '#submit' => ['::iconSubmitForm'],
    '#limit_validation_errors' => [],
    '#ajax' => [
    'callback' => '::ajaxSubmitForm',
    'progress' => [
    'type' => 'none',
  ],
],
  ];
}

    $form['search_keyword'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Keyword'),
      '#default_value' => $this->getKeyword(),
    ];
    $form['search_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'search-button'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if(strlen($form_state->getValue('search_element_type')) < 1) {
      $form_state->setErrorByName('search_element_type', $this->t('Please select an element type'));
    }
  }

  /**
   * {@inheritdoc}
   */
  private function redirectUrl(FormStateInterface $form_state) {
    $this->setKeyword($form_state->getValue('search_keyword'));
    if ($this->getKeyword() == NULL || $this->getKeyword() == '') {
      $this->setKeyword("_");
    }

    // ^TODO: must be removed in the future
    if ($form_state->getValue('search_element_type') === 'stream2') {
      $form_state->setValue('search_element_type', 'stream');
    }

    // IF ELEMENT TYPE IS CLASS
    if ($form_state->getValue('search_element_type') == 'platform') {
      $url = Url::fromRoute('rep.browse_tree');
      $url->setRouteParameter('mode', 'browse');
      $url->setRouteParameter('elementtype', $form_state->getValue('search_element_type'));
      return $url;
    }

    // IF ELEMENT TYPE IS INSTANCE
    $url = Url::fromRoute('dpl.list_element');
    $url->setRouteParameter('elementtype', $form_state->getValue('search_element_type'));
    $url->setRouteParameter('keyword', $this->getKeyword());
    $url->setRouteParameter('page', $this->getPage());
    $url->setRouteParameter('pagesize', $this->getPageSize());
    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxSubmitForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $this->setPage(1);
    $this->setPageSize(12);
    $url = $this->redirectUrl($form_state);
    $response->addCommand(new RedirectCommand($url->toString()));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $url = $this->redirectUrl($form_state);
    $form_state->setRedirectUrl($url);
  }

}
