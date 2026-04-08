<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rep\ListKeywordPage;
use Drupal\rep\Entity\Platform;
use Drupal\rep\Entity\Deployment;
use Drupal\rep\Entity\Stream;
use Drupal\rep\Entity\VSTOIInstance;

class DPLListForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dpl_list_form';
  }

  protected $list;

  protected $list_size;

  public function getList() {
    return $this->list;
  }

  public function setList($list) {
    return $this->list = $list;
  }

  public function getListSize() {
    return $this->list_size;
  }

  public function setListSize($list_size) {
    return $this->list_size = $list_size;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $elementtype=NULL, $keyword=NULL, $page=NULL, $pagesize=NULL) {

    $page = $page ?? 1;
    $pagesize = $pagesize ?? 12;

    // Keyword filter (defaults from route)
    $text_filter = $form_state->getValue('text_filter');
    if ($text_filter === NULL) {
      $text_filter = ($keyword !== NULL && $keyword !== '_' ? $keyword : '');
    }
    $keyword_param = ($text_filter === NULL || $text_filter === '') ? '_' : $text_filter;

    // GET TOTAL NUMBER OF ELEMENTS AND TOTAL NUMBER OF PAGES
    $this->setListSize(-1);
    if ($elementtype != NULL) {
      $this->setListSize(ListKeywordPage::total($elementtype, $keyword_param));
    }
    $total_pages = 1;
    if (is_numeric($this->list_size) && $pagesize > 0) {
      $size = (int) $this->list_size;
      if ($size > 0) {
        $total_pages = (int) ceil($size / $pagesize);
      }
    }

    // Clamp current page
    $page = max(1, min((int) $page, (int) $total_pages));

    // CREATE LINK FOR NEXT PAGE AND PREVIOUS PAGE
    if ($page < $total_pages) {
      $next_page = $page + 1;
      $next_page_link = ListKeywordPage::link($elementtype, $keyword_param, $next_page, $pagesize);
    } else {
      $next_page_link = '';
    }
    if ($page > 1) {
      $previous_page = $page - 1;
      $previous_page_link = ListKeywordPage::link($elementtype, $keyword_param, $previous_page, $pagesize);
    } else {
      $previous_page_link = '';
    }

    // RETRIEVE ELEMENTS
    $this->setList(ListKeywordPage::exec($elementtype, $keyword_param, $page, $pagesize));

    $preferred_instrument = \Drupal::config('rep.settings')->get('preferred_instrument') ?? 'Instrument';
    $preferred_component = \Drupal::config('rep.settings')->get('preferred_component') ?? 'Component';
    $preferred_platform = \Drupal::config('rep.settings')->get('preferred_platform') ?? 'Platform';

    $platform_label = ucfirst($preferred_platform);
    $platform_plural = preg_match('/[^aeiou]y$/i', $platform_label)
      ? substr($platform_label, 0, -1) . 'ies'
      : $platform_label . 's';

    $class_name = "";
    switch ($elementtype) {

      // PLATFORM
      case "platform":
        $class_name = $platform_plural;
        $header = Platform::generateHeader();
        $output = Platform::generateOutput($this->getList());
        break;

      // STREAM
      case "stream":
        $class_name = 'Message Stream' . "s";
        $header = Stream::generateHeader();
        $output = Stream::generateOutput($this->getList());
        break;

      // DEPLOYMENT
      case "deployment":
        $class_name = $preferred_instrument . "s";
        $header = Deployment::generateHeader();
        $output = Deployment::generateOutput($this->getList());
        break;

      // PLATFORM INSTANCE
      case "platforminstance":
        $class_name = $platform_label . " Instances";
        $header = VSTOIInstance::generateHeader($elementtype);
        $output = VSTOIInstance::generateOutput($elementtype, $this->getList());
        break;

      // INSTRUMENT INSTANCE
      case "instrumentinstance":
        $class_name = $preferred_instrument . " Instances";
        $header = VSTOIInstance::generateHeader($elementtype);
        $output = VSTOIInstance::generateOutput($elementtype, $this->getList());
        break;

      // COMPONENT INSTANCE
      case "componentinstance":
        $class_name = $preferred_component . " Instances";
        $header = VSTOIInstance::generateHeader($elementtype);
        $output = VSTOIInstance::generateOutput($elementtype, $this->getList());
        break;

      // UNKNOWN
      default:
        $class_name = "Objects of Unknown Types";
    }

    $form['header'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['header-container'],
        'style' => 'display: flex; justify-content: space-between; align-items: center;',
      ],
    ];
    $form['header']['title'] = [
      '#type' => 'item',
      '#markup' => t('<h3>Available <font style="color:DarkGreen;">' . $class_name . '</font></h3>'),
    ];

    // Filter UI
    $form['filter_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['d-flex', 'ms-auto', 'mb-0'],
        'style' => 'margin-bottom:0!important;'
      ],
    ];

    $form['filter_container']['filter_label'] = [
      '#type' => 'label',
      '#title' => $this->t('Filter(s): '),
      '#attributes' => [
        'class' => ['pt-3', 'me-2', 'fw-bold'],
      ]
    ];

    $form['filter_container']['text_filter'] = [
      '#type' => 'textfield',
      '#default_value' => $text_filter,
      '#ajax' => [
        'callback' => '::ajaxReloadTable',
        'wrapper' => 'element-table-wrapper',
        'event' => 'change',
      ],
      '#attributes' => [
        'class' => ['form-select', 'w-auto', 'mt-2', 'me-1'],
        'style' => 'max-width:230px;margin-bottom:0!important;float:right;',
        'placeholder' => 'Type in your search criteria',
        'onkeydown' => 'if (event.keyCode == 13) { event.preventDefault(); this.blur(); }',
      ],
    ];

    // Table + pager wrapper (AJAX)
    $form['element_table_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'element-table-wrapper'],
    ];

    $form['element_table_wrapper']['element_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $output,
      '#empty' => t('No results found'),
    ];

    $form['element_table_wrapper']['pager'] = [
      '#theme' => 'list-page',
      '#items' => [
        'page' => strval($page),
        'first' => ListKeywordPage::link($elementtype, $keyword_param, 1, $pagesize),
        'last' => ListKeywordPage::link($elementtype, $keyword_param, $total_pages, $pagesize),
        'previous' => $previous_page_link,
        'next' => $next_page_link,
        'last_page' => strval($total_pages),
        'links' => null,
        'title' => ' ',
      ],
    ];

    return $form;
  }

  /**
   * AJAX callback to reload list when filters change.
   */
  public function ajaxReloadTable(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    return $form['element_table_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
