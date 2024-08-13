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

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // RETRIEVE PARAMETERS FROM HTML REQUEST
    $request = \Drupal::request();
    $pathInfo = $request->getPathInfo();
    $pathElements = (explode('/',$pathInfo));
    $this->setElementType('platform');
    $this->setKeyword('');
    $this->setPage(1);
    $this->setPageSize(12);
    if (sizeof($pathElements) >= 7) {

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

    $form['search_element_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Element Type'),
      '#required' => TRUE,
      '#options' => [
        //'dp2' => $this->t('DP2s'),
        //'str' => $this->t('STRs'),
        'platform' => $this->t('Platforms'),
        'platforminstance' => $this->t('Platform Instances'),
        'instrumentinstance' => $this->t('Instrument Instances'),
        'detectorinstance' => $this->t('Detector Instances'),
        'deployment' => $this->t('Deployments'),
        'stream' => $this->t('Streams'),
      ],
      '#default_value' => $this->getElementType(),
      '#ajax' => [
        'callback' => '::ajaxSubmitForm',
      ],
    ];
    $form['search_keyword'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Keyword'),
      '#default_value' => $this->getKeyword(),
    ];
    $form['search_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
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