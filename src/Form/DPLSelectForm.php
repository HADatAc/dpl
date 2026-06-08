<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\ListManagerEmailPage;
use Drupal\rep\ManageOwnerFilter;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Platform;
use Drupal\rep\Entity\Stream;
use Drupal\rep\Entity\Deployment;
use Drupal\rep\Entity\VSTOIInstance;
use Drupal\rep\Vocabulary\VSTOI;

class DPLSelectForm extends FormBase {

  private const KEYWORD_SCAN_LIMIT = 9999;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dpl_search_form';
  }

  public $element_type;

  public $manager_email;

  public $manager_name;

  public $single_class_name;

  public $plural_class_name;

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
  public function buildForm(array $form, FormStateInterface $form_state, $elementtype=NULL, $page=NULL, $pagesize=NULL)
  {
    // GET MANAGER EMAIL
    $authenticated_manager_email = \Drupal::currentUser()->getEmail();
    $this->manager_email = $authenticated_manager_email;
    $uid = \Drupal::currentUser()->id();
    $user = \Drupal\user\Entity\User::load($uid);
    $this->manager_name = $user->name->value;

    // GET ELEMENT TYPE
    // Keep backward compatibility with older links that still use "instance".
    $legacy_type_alias = [
      'instance' => 'instrumentinstance',
    ];
    $this->element_type = $legacy_type_alias[$elementtype] ?? $elementtype;
    if ($page === NULL) {
      $page = 1;
    }

    // SET PAGE_SIZE
    $pagesize = $form_state->get('page_size') ?? $pagesize ?? 9;
    $form_state->set('page_size', $pagesize);

    /// GET VIEW MODE + FILTER STATE
    $session = \Drupal::request()->getSession();
    $view_type = $form_state->get('view_type') ?? $session->get('dpl_select_view_type') ?? 'table';
    $form_state->set('view_type', $view_type);
    $table_active_class = ($view_type === 'table') ? ['selected-button'] : [];
    $card_active_class = ($view_type === 'card') ? ['selected-button'] : [];

    $form['#attached']['library'][] = 'dpl/dpl_manage_filters';

    // Pagination vars (defined upfront so they can be adjusted after keyword filtering).
    $total_pages = 1;
    $next_page_link = '';
    $previous_page_link = '';

    if ($view_type === 'card') {
      $form['#attached']['library'][] = 'rep/infinitescroll';
    }

    $status_filter = $form_state->getValue('status_filter');
    if ($status_filter === NULL) {
      $status_filter = $session->get('dpl_select_status_filter', '_');
    }
    else {
      $session->set('dpl_select_status_filter', $status_filter);
    }

    // Keyword filter (persisted per element type)
    $text_filter_key = 'dpl_select_text_filter.' . (string) $this->element_type;
    $text_filter = $form_state->getValue('text_filter');
    if ($text_filter === NULL) {
      $text_filter = $session->get($text_filter_key, '');
    }
    else {
      $session->set($text_filter_key, $text_filter);
    }
    $text_filter = trim((string) $text_filter);
    $keyword_active = ($text_filter !== '');

    $is_admin = ManageOwnerFilter::isAdmin();
    $manager_filter_key = 'dpl_select_manager_filter.' . (string) $this->element_type;
    $manager_filter = $form_state->getValue('manager_filter');
    if ($manager_filter === NULL) {
      $manager_filter = $session->get($manager_filter_key, '');
    }
    else {
      $manager_filter = ManageOwnerFilter::normalizeSelectedEmail($manager_filter);
      $session->set($manager_filter_key, $manager_filter);
    }

    $effective_manager_email = ManageOwnerFilter::resolveEffectiveOwner($authenticated_manager_email, $manager_filter, $status_filter);

    if ($view_type == 'table') {

      // Total + list (optionally filtered by status)
      $this->setListSize(-1);
      if ($this->element_type != NULL) {
        if ($status_filter === '_' || $status_filter === NULL || $status_filter === '') {
          $this->setListSize(ListManagerEmailPage::total($this->element_type, $effective_manager_email));
        }
        else {
          $this->setListSize(ListManagerEmailPage::totalByStatusManagerEmail($this->element_type, $status_filter, $effective_manager_email, FALSE));
        }
      }

      // When keyword filter is active, fetch a larger slice for in-memory filtering.
      if ($keyword_active) {
        $base_total = $this->getListSize();
        $scan_limit = self::KEYWORD_SCAN_LIMIT;
        $fetch_size = $scan_limit;
        if (is_numeric($base_total)) {
          $fetch_size = min($scan_limit, max(0, (int) $base_total));
        }

        if ($fetch_size > 0) {
          if ($status_filter === '_' || $status_filter === NULL || $status_filter === '') {
            $this->setList(ListManagerEmailPage::exec($this->element_type, $effective_manager_email, 1, $fetch_size));
          }
          else {
            $this->setList(ListManagerEmailPage::execByStatusManagerEmail($this->element_type, $status_filter, $effective_manager_email, FALSE, 1, $fetch_size));
          }
        }
        else {
          $this->setList([]);
        }

        // Pagination will be recomputed after filtering $output.
        $form_state->set('current_page', $page);
        $form_state->set('page_size', $pagesize);
      }
      else {
        // Compute total pages (at least 1)
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
          $next_page_link = ListManagerEmailPage::link($this->element_type, $next_page, $pagesize);
        } else {
          $next_page_link = '';
        }
        if ($page > 1) {
          $previous_page = $page - 1;
          $previous_page_link = ListManagerEmailPage::link($this->element_type, $previous_page, $pagesize);
        } else {
          $previous_page_link = '';
        }

        $form_state->set('current_page', $page);
        $form_state->set('page_size', $pagesize);

        if ($status_filter === '_' || $status_filter === NULL || $status_filter === '') {
          $this->setList(ListManagerEmailPage::exec($this->element_type, $effective_manager_email, $page, $pagesize));
        }
        else {
          $this->setList(ListManagerEmailPage::execByStatusManagerEmail($this->element_type, $status_filter, $effective_manager_email, FALSE, $page, $pagesize));
        }
      }
    } else {
      // SET PAGE_SIZE
      $pagesize = $form_state->get('page_size') ?? $pagesize ?? 9;
      $form_state->set('page_size', $pagesize);

      // Total + list (optionally filtered by status) for card view too.
      $this->setListSize(-1);
      if ($this->element_type != NULL) {
        if ($status_filter === '_' || $status_filter === NULL || $status_filter === '') {
          $this->setListSize(ListManagerEmailPage::total($this->element_type, $effective_manager_email));
        }
        else {
          $this->setListSize(ListManagerEmailPage::totalByStatusManagerEmail($this->element_type, $status_filter, $effective_manager_email, FALSE));
        }
      }

      $fetch_size = $pagesize;
      if ($keyword_active) {
        $scan_limit = self::KEYWORD_SCAN_LIMIT;
        $size = $this->getListSize();
        $fetch_size = $scan_limit;
        if (is_numeric($size)) {
          $size_int = (int) $size;
          if ($size_int > 0) {
            $fetch_size = min($scan_limit, $size_int);
          }
          elseif ($size_int === 0) {
            $fetch_size = 0;
          }
        }
      }

      if ($fetch_size <= 0) {
        $this->setList([]);
      }
      elseif ($status_filter === '_' || $status_filter === NULL || $status_filter === '') {
        $this->setList(ListManagerEmailPage::exec($this->element_type, $effective_manager_email, 1, $fetch_size));
      }
      else {
        $this->setList(ListManagerEmailPage::execByStatusManagerEmail($this->element_type, $status_filter, $effective_manager_email, FALSE, 1, $fetch_size));
      }
    }

    $this->single_class_name = "";
    $this->plural_class_name = "";

    $preferred_instrument = \Drupal::config('rep.settings')->get('preferred_instrument') ?? 'Instrument';
    $preferred_component = \Drupal::config('rep.settings')->get('preferred_component') ?? 'Component';
    $preferred_platform = \Drupal::config('rep.settings')->get('preferred_platform') ?? 'Platform';

    $platform_label = ucfirst($preferred_platform);
    $platform_plural = preg_match('/[^aeiou]y$/i', $platform_label)
      ? substr($platform_label, 0, -1) . 'ies'
      : $platform_label . 's';

    // Safe defaults to avoid uninitialized-variable fatals for unknown types.
    $header = [];
    $output = [];
    $outputCard = [];

    switch ($this->element_type) {

      // PLATFORM
      case "platform":
        $this->single_class_name = $platform_label;
        $this->plural_class_name = $platform_plural;
        $header = Platform::generateHeader();
        $output = Platform::generateOutput($this->getList());
        $outputCard = Platform::generateCardOutput($this->getList());
        break;

      // PLATFORM INSTANCE
      case "platforminstance":
        $this->single_class_name = $platform_label . " Instance";
        $this->plural_class_name = $platform_label . " Instances";
        $header = VSTOIInstance::generateHeader($this->element_type);
        $output = VSTOIInstance::generateOutput($this->element_type, $this->getList());
        $outputCard = VSTOIInstance::generateCardOutput($this->element_type, $this->getList());
        break;

      // INSTRUMENT INSTANCE
      case "instrumentinstance":
        $this->single_class_name = $preferred_instrument . " Instance";
        $this->plural_class_name = $preferred_instrument . " Instances";
        $header = VSTOIInstance::generateHeader($this->element_type);
        $output = VSTOIInstance::generateOutput($this->element_type, $this->getList());
        $outputCard = VSTOIInstance::generateCardOutput($this->element_type, $this->getList());
        break;

      // COMPONENT INSTANCE
      case "componentinstance":
        $this->single_class_name = $preferred_component . " Instance";
        $this->plural_class_name = $preferred_component . " Instances";
        $header = VSTOIInstance::generateHeader($this->element_type);
        $output = VSTOIInstance::generateOutput($this->element_type, $this->getList());
        $outputCard = VSTOIInstance::generateCardOutput($this->element_type, $this->getList());
        break;

      // STREAM
      case "stream":
        $this->single_class_name = "Stream";
        $this->plural_class_name = "Streams";
        $header = Stream::generateHeader();
        $output = $outputCard = Stream::generateOutput($this->getList());
        break;

      // DEPLOYMENT
      case "deployment":
        $this->single_class_name = "Deployment";
        $this->plural_class_name = "Deployments";
        $header = Deployment::generateHeader();
        $output = $outputCard = Deployment::generateOutput($this->getList());
        break;

      default:
        $this->single_class_name = "Object of Unknown Type";
        $this->plural_class_name = "Objects of Unknown Types";
        $header = [];
        $output = [];
        $outputCard = [];
    }

    // Apply in-memory keyword filtering to the table view output.
    if ($view_type == 'table' && $keyword_active) {
      $filtered_output = $this->filterOutputByKeyword($output, $text_filter);
      $filtered_total = count($filtered_output);
      $this->setListSize($filtered_total);

      $total_pages = 1;
      if ($filtered_total > 0 && $pagesize > 0) {
        $total_pages = (int) ceil($filtered_total / $pagesize);
      }
      $page = max(1, min((int) $page, (int) $total_pages));
      $form_state->set('current_page', $page);

      $previous_page_link = ($page > 1)
        ? ListManagerEmailPage::link($this->element_type, $page - 1, $pagesize)
        : '';
      $next_page_link = ($page < $total_pages)
        ? ListManagerEmailPage::link($this->element_type, $page + 1, $pagesize)
        : '';

      $offset = ($page <= 1) ? 0 : (($page - 1) * $pagesize);
      $output = array_slice($filtered_output, $offset, $pagesize, TRUE);
    }

    // Apply in-memory keyword filtering to the card view output.
    if ($view_type == 'card' && $keyword_active) {
      $filtered_output_card = $this->filterOutputByKeyword($outputCard, $text_filter);
      $filtered_total = count($filtered_output_card);
      $this->setListSize($filtered_total);

      $current_page_size = (int) ($form_state->get('page_size') ?? $pagesize ?? 9);
      if ($current_page_size > 0) {
        $outputCard = array_slice($filtered_output_card, 0, $current_page_size, TRUE);
      }
      else {
        $outputCard = [];
      }
    }

    // PUT FORM TOGETHER
    $form['page_title'] = [
      '#type' => 'item',
      '#title' => $this->t('<h3 class="mt-5">Manage ' . $this->plural_class_name . '</h3>'),
    ];
    $form['page_subtitle'] = [
      '#type' => 'item',
      '#title' => $this->t('<h4>' . $this->plural_class_name . ' maintained by <font color="DarkGreen">' . $this->manager_name . ' (' . $this->manager_email . ')</font></h4>'),
    ];

    $show_owner_indicator = $is_admin && $manager_filter !== '' && strcasecmp($effective_manager_email, $manager_filter) === 0;
    if ($show_owner_indicator) {
      $form['owner_indicator'] = [
        '#type' => 'item',
        '#markup' => $this->t('<div class="alert alert-info py-2 mb-3"><strong>A visualizar owner:</strong> @owner</div>', [
          '@owner' => $effective_manager_email,
        ]),
      ];
    }

    // ADD BUTTONS FOR VIEW MODE
    $form['view_toggle'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['view-toggle', 'd-flex', 'justify-content-end']],
    ];

    $form['view_toggle']['table_view'] = [
      '#type' => 'submit',
      '#value' => '',
      '#name' => 'view_table',
      '#attributes' => [
        'style' => 'padding: 20px;',
        'class' => array_merge(['table-view-button', 'fa-xl', 'mx-1'], $table_active_class),
        'title' => $this->t('Table View'),
      ],
      '#submit' => ['::viewTableSubmit'],
      '#limit_validation_errors' => [],
    ];

    $form['view_toggle']['card_view'] = [
      '#type' => 'submit',
      '#value' => '',
      '#name' => 'view_card',
      '#attributes' => [
        'style' => 'padding: 20px;',
        'class' => array_merge(['card-view-button', 'fa-xl'], $card_active_class),
        'title' => $this->t('Card View'),
      ],
      '#submit' => ['::viewCardSubmit'],
      '#limit_validation_errors' => [],
    ];

    // Actions row (Add + filters)
    $form['actions_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['d-flex', 'flex-column', 'align-items-stretch', 'mb-0'],
      ],
    ];

    $form['actions_wrapper']['buttons_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['d-flex', 'gap-2', 'flex-nowrap', 'justify-content-start', 'mb-2'],
        'style' => 'flex-wrap:nowrap;overflow-x:auto;'
      ],
    ];

    $form['actions_wrapper']['buttons_container']['add_element'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add New ' . $this->single_class_name),
      '#name' => 'add_element',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'add-element-button'],
      ],
    ];

    if ($view_type == 'table') {
      $form['actions_wrapper']['buttons_container']['edit_selected_element'] = [
        '#type' => 'submit',
        '#value' => $this->t('Edit ' . $this->single_class_name . ' Selected'),
        '#name' => 'edit_element',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'edit-element-button'],
        ],
      ];

      $form['actions_wrapper']['buttons_container']['delete_selected_element'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete ' . $this->plural_class_name . ' Selected'),
        '#name' => 'delete_element',
        '#attributes' => [
          'onclick' => 'if(!confirm("Really Delete?")){return false;}',
          'class' => ['btn', 'btn-primary', 'delete-element-button'],
        ],
      ];

      if ($this->element_type == 'componentstem') {
        $form['actions_wrapper']['buttons_container']['derive_componentstem'] = [
          '#type' => 'submit',
          '#value' => $this->t('Derive New ' . $preferred_component . ' Stem from Selected'),
          '#name' => 'derive_componentstem',
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'derive-button'],
          ],
        ];
      }
    }

    $status_options = [
      '_' => $this->t('All Status'),
      VSTOI::DRAFT => $this->t('Draft'),
      VSTOI::UNDER_REVIEW => $this->t('Under Review'),
      VSTOI::CURRENT => $this->t('Current'),
      VSTOI::DEPLOYED => $this->t('Deployed'),
      VSTOI::DAMAGED => $this->t('Damaged'),
      VSTOI::DEPRECATED => $this->t('Deprecated'),
    ];

    $has_active_filters = ($text_filter !== '')
      || ($status_filter !== '_' && $status_filter !== NULL && $status_filter !== '')
      || ($is_admin && trim((string) $manager_filter) !== '');

    $ajax_wrapper = ($view_type === 'card') ? 'cards-lazy-wrapper' : 'element-table-wrapper';
    $ajax_callback = ($view_type === 'card') ? '::ajaxReloadCards' : '::ajaxReloadTable';

    $form['actions_wrapper']['filters_panel'] = [
      '#type' => 'details',
      '#title' => $this->t('Filter(s)'),
      '#open' => $has_active_filters,
      '#attributes' => [
        'class' => ['dpl-manage-filters-panel', 'w-100'],
      ],
    ];

    $form['actions_wrapper']['filters_panel']['filter_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['row', 'g-2', 'align-items-end', 'dpl-manage-filters'],
      ],
    ];

    $form['actions_wrapper']['filters_panel']['filter_container']['text_filter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Keyword'),
      '#title_display' => 'invisible',
      '#default_value' => $text_filter,
      '#prefix' => '<div class="col-12 col-lg-4">',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => $ajax_callback,
        'wrapper' => $ajax_wrapper,
        'event' => 'change',
      ],
      '#attributes' => [
        'class' => ['form-control'],
        'placeholder' => $this->t('Type in your search criteria'),
        'onkeydown' => 'if (event.keyCode == 13) { event.preventDefault(); this.blur(); }',
      ],
    ];

    if ($is_admin) {
      $form['actions_wrapper']['filters_panel']['filter_container']['manager_filter'] = [
        '#type' => 'textfield',
        '#title' => $this->t('User'),
        '#title_display' => 'invisible',
        '#default_value' => $manager_filter,
        '#prefix' => '<div class="col-12 col-lg-4">',
        '#suffix' => '</div>',
        '#ajax' => [
          'callback' => $ajax_callback,
          'wrapper' => $ajax_wrapper,
          'event' => 'change',
        ],
        '#attributes' => [
          'class' => ['form-control'],
          'placeholder' => $this->t('User email (Draft/Under Review)'),
        ],
      ];
    }

    $form['actions_wrapper']['filters_panel']['filter_container']['status_filter'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#title_display' => 'invisible',
      '#options' => $status_options,
      '#default_value' => $status_filter,
      '#prefix' => '<div class="col-12 col-md-4 col-lg-2">',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => $ajax_callback,
        'wrapper' => $ajax_wrapper,
        'event' => 'change',
      ],
      '#attributes' => [
        'class' => ['form-select'],
      ],
    ];

    $form['actions_wrapper']['filters_panel']['filter_container']['clear_filters'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear Filters'),
      '#name' => 'clear_filters',
      '#limit_validation_errors' => [],
      '#prefix' => '<div class="col-12 col-md-4 col-lg-2 d-grid">',
      '#suffix' => '</div>',
      '#attributes' => [
        'class' => ['btn', 'btn-outline-secondary'],
      ],
    ];

    // RENDER BASED ON VIEW TYPE
    if ($view_type == 'table') {
      $form['element_table_wrapper'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'element-table-wrapper'],
      ];

      $this->buildTableView($form['element_table_wrapper'], $form_state, $header, $output);

      $form['element_table_wrapper']['pager'] = [
        '#theme' => 'list-page',
        '#items' => [
          'page' => strval($page),
          'first' => ListManagerEmailPage::link($this->element_type, 1, $pagesize),
          'last' => ListManagerEmailPage::link($this->element_type, $total_pages, $pagesize),
          'previous' => $previous_page_link,
          'next' => $next_page_link,
          'last_page' => strval($total_pages),
          'links' => null,
          'title' => ' ',
        ],
      ];

    } elseif ($view_type == 'card') {
      $form['cards_lazy_wrapper'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'cards-lazy-wrapper'],
      ];

      $this->buildCardView($form['cards_lazy_wrapper'], $form_state, $header, $outputCard);

      $form['cards_lazy_wrapper']['records_count'] = [
        '#type' => 'item',
        '#markup' => $this->t('<div id="count-cards" style="font-weight:bold; margin-top:10px; padding-right:2rem;">Currently viewing @count of @total @class</div>', [
          '@count' => count($this->getList()),
          '@total' => (int) $this->getListSize(),
          '@class' => $this->plural_class_name,
        ]),
      ];

      $total_items = $this->getListSize();
      $current_page_size = $form_state->get('page_size') ?? 9;

      if ($total_items > $current_page_size) {
        $form['cards_lazy_wrapper']['load_more'] = [
          '#type' => 'submit',
          '#value' => $this->t('Load More'),
          '#name' => 'load_more',
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'load-more-button'],
            'id' => 'load-more-button',
            'style' => 'display: none;',
          ],
          '#submit' => ['::loadMoreSubmit'],
          '#ajax' => [
            'callback' => '::ajaxReloadCards',
            'wrapper' => 'cards-lazy-wrapper',
            'event' => 'click',
          ],
          '#limit_validation_errors' => [],
        ];

        // ADD LOADING OVERLAY
        $form['cards_lazy_wrapper']['loading_overlay'] = [
          '#type' => 'container',
          '#attributes' => [
            'id' => 'loading-overlay',
            'class' => ['loading-overlay'],
            'style' => 'display: none;',
          ],
          '#markup' => '<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>',
        ];

        $form['cards_lazy_wrapper']['list_state'] = [
          '#type' => 'hidden',
          '#value' => ($this->getListSize() > $form_state->get('page_size')) ? 1 : 0,
          '#attributes' => [
            'id' => 'list_state',
          ],
        ];
      }
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#name' => 'back',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'back-button'],
      ],
    ];
    $form['space'] = [
      '#type' => 'item',
      '#value' => $this->t('<br><br><br>'),
    ];

    return $form;
  }

  /**
   * AJAX callback to reload the table wrapper when filters change.
   */
  public function ajaxReloadTable(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    return $form['element_table_wrapper'];
  }

  /**
   * AJAX callback to reload cards wrapper when loading more.
   */
  public function ajaxReloadCards(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);

    $triggering_element = $form_state->getTriggeringElement();
    $trigger_name = (string) ($triggering_element['#name'] ?? '');

    if ($trigger_name === 'load_more') {
      $response = new AjaxResponse();

      $previous = (int) ($form_state->get('previous_page_size') ?? 0);
      $cards_container = $form['cards_lazy_wrapper']['element_cards_wrapper'] ?? [];

      $card_keys = [];
      if (is_array($cards_container)) {
        foreach (array_keys($cards_container) as $key) {
          if (is_string($key) && $key !== '' && $key[0] !== '#') {
            $card_keys[] = $key;
          }
        }
      }

      $previous = max(0, min($previous, count($card_keys)));
      $new_keys = array_slice($card_keys, $previous);
      $append_build = [];
      foreach ($new_keys as $k) {
        $append_build[$k] = $cards_container[$k];
      }

      if (!empty($append_build)) {
        $rendered = (string) \Drupal::service('renderer')->renderPlain($append_build);
        if (trim($rendered) !== '') {
          $response->addCommand(new AppendCommand('#element-cards-wrapper', $rendered));
        }
      }

      $loaded = count($card_keys);
      $total = (int) ($this->getListSize() ?? 0);
      $has_more = $total > $loaded;

      $count_markup = '<div id="count-cards" style="font-weight:bold; margin-top:10px; padding-right:2rem;">'
        . $this->t('Currently viewing @count of @total @class', [
          '@count' => $loaded,
          '@total' => $total,
          '@class' => $this->plural_class_name,
        ])
        . '</div>';
      $response->addCommand(new ReplaceCommand('#count-cards', $count_markup));

      $response->addCommand(new InvokeCommand('#list_state', 'val', [$has_more ? 1 : 0]));
      if (!$has_more) {
        $response->addCommand(new InvokeCommand('#load-more-button', 'hide', []));
      }

      $response->addCommand(new InvokeCommand('html, body', 'animate', [
        ['scrollTop' => 99999],
        'slow',
      ]));

      return $response;
    }

    return $form['cards_lazy_wrapper'];
  }

  /**
   * Clear persisted table filters for the current element type.
   */
  protected function clearSavedFilters(FormStateInterface $form_state): void {
    $session = \Drupal::request()->getSession();
    $suffix = (string) $this->element_type;

    $session->remove('dpl_select_status_filter');
    $session->remove('dpl_select_text_filter.' . $suffix);
    $session->remove('dpl_select_manager_filter.' . $suffix);

    $input = $form_state->getUserInput();
    unset($input['text_filter'], $input['manager_filter'], $input['status_filter']);
    $form_state->setUserInput($input);

    $form_state->setValue('text_filter', '');
    $form_state->setValue('manager_filter', '');
    $form_state->setValue('status_filter', '_');
    $form_state->setRebuild(TRUE);
  }

  /**
   * Filters the given $output rows by $keyword (case-insensitive).
   *
   * Searches across all scalar fields in each row, stripping HTML tags.
   */
  protected function filterOutputByKeyword(array $output, string $keyword): array {
    $needle = trim($keyword);
    if ($needle === '') {
      return $output;
    }

    $needle = function_exists('mb_strtolower') ? mb_strtolower($needle) : strtolower($needle);
    $filtered = [];

    foreach ($output as $row_key => $row) {
      if (!is_array($row)) {
        continue;
      }

      foreach ($row as $value) {
        if (is_array($value)) {
          $value_str = strip_tags((string) \Drupal::service('renderer')->renderPlain($value));
        }
        else {
          $value_str = strip_tags((string) $value);
        }

        $value_str = function_exists('mb_strtolower') ? mb_strtolower($value_str) : strtolower($value_str);
        if ($value_str !== '' && strpos($value_str, $needle) !== FALSE) {
          $filtered[$row_key] = $row;
          break;
        }
      }
    }

    return $filtered;
  }

  /**
   * BUILD TABLE VIEW
   */
  protected function buildTableView(array &$form, FormStateInterface $form_state, $header, $output)
  {
    $form['element_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $output,
      '#js_select' => FALSE,
      '#empty' => $this->t('No ' . $this->plural_class_name . ' found'),
    ];
  }

  /**
   * BUILD CARD VIEW
   */
  protected function buildCardView(array &$form, FormStateInterface $form_state, $header, $output)
  {
    $preferred_component = \Drupal::config('rep.settings')->get('preferred_component') ?? 'Component';

    $form['element_cards_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'element-cards-wrapper', 'class' => ['row', 'mt-3']],
    ];

    if (empty($output)) {
      $form['element_cards_wrapper']['no_results'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['col-12']],
        'message' => [
          '#markup' => '<div class="alert alert-info mb-0">'
            . $this->t('No @items found for the current filters.', ['@items' => $this->plural_class_name])
            . '</div>',
        ],
      ];
      return;
    }

    foreach ($output as $key => $item) {
      $sanitized_key = md5($key);

      $form['element_cards_wrapper'][$sanitized_key] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['col-md-4', 'mt-3']],
      ];

      $form['element_cards_wrapper'][$sanitized_key]['card'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['card', 'mb-4']],
      ];

      $header_text = '';

      foreach ($header as $column_key => $column_label) {
        if ($column_label == 'Name') {
          $value = isset($item[$column_key]) ? $item[$column_key] : '';
          $header_text = strip_tags($value);
          break;
        }
      }

      if (strlen($header_text) > 0) {
        $form['element_cards_wrapper'][$sanitized_key]['card']['header'] = [
          '#type' => 'container',
          '#attributes' => [
            'style' => 'margin-bottom:0!important;',
            'class' => ['card-header'],
          ],
          '#markup' => '<h5 class="mb-0">' . $header_text . '</h5>',
        ];
      }

      $form['element_cards_wrapper'][$sanitized_key]['card']['content_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['row'],
        ],
      ];

      // Image Column
      $image_uri = Utils::getAPIImage($item['element_uri'], $item['element_image'], UTILS::placeholderImage($item['element_hascotypeuri'],$this->element_type, '/'));
      $form['element_cards_wrapper'][$sanitized_key]['card']['content_wrapper']['image'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['col-md-5', 'text-align-center'],
          'style' => 'text-align:center!important;margin-bottom:0px;',
        ],
        'image' => [
          '#type' => 'html_tag',
          '#tag' => 'img',
          '#attributes' => [
              'src' => $image_uri,
              'alt' => $header_text,
              'style' => 'max-width: 70%; height: auto;',
              'class' => ['img-fluid', 'mb-3', 'border', 'border-5', 'rounded', 'rounded-5', 'p-3'],
          ]
        ],
      ];

      // Content Column
      $form['element_cards_wrapper'][$sanitized_key]['card']['content_wrapper']['content'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['col-md-7', 'card-body', 'justify-content-center'],
          'style' => 'margin-bottom:0!important;',
        ],
      ];
      // Loop through each header column and add it to the card
      foreach ($header as $column_key => $column_label) {
        $value = isset($item[$column_key]) ? $item[$column_key] : '';
        if ($column_label == 'Name') {
          continue;
        }

        if ($column_label == 'Status') {
          $value_rendered = [
            '#markup' => $value,
            '#allowed_tags' => ['b', 'font', 'span', 'div', 'strong', 'em'],
          ];
        } else {
          $value_rendered = [
            '#markup' => $value,
          ];
        }

        $form['element_cards_wrapper'][$sanitized_key]['card']['content_wrapper']['content'][$column_key] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['field-container'],
          ],
          'label' => [
            '#type' => 'html_tag',
            '#tag' => 'strong',
            '#value' => $column_label . ': ',
          ],
          'value' => $value_rendered,
        ];
      }


      $form['element_cards_wrapper'][$sanitized_key]['card']['footer'] = [
        '#type' => 'container',
        '#attributes' => [
          'style' => 'margin-bottom:0!important;',
          'class' => ['d-flex', 'card-footer', 'justify-content-end'],
        ],
      ];

      $form['element_cards_wrapper'][$sanitized_key]['card']['footer']['actions'] = [
        '#type' => 'actions',
        '#attributes' => [
          'style' => 'margin-bottom:0!important;',
          'class' => ['mb-0'],
        ],
      ];

      // EDIT BUTTON
      $form['element_cards_wrapper'][$sanitized_key]['card']['footer']['actions']['edit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Edit'),
        '#name' => 'edit_element_' . $sanitized_key,
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'btn-sm', 'edit-element-button'],
        ],
        '#submit' => ['::editElementSubmit'],
        '#limit_validation_errors' => [],
        '#element_uri' => $key,
      ];

      // DELETE BUTTON
      $form['element_cards_wrapper'][$sanitized_key]['card']['footer']['actions']['delete'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete'),
        '#name' => 'delete_element_' . $sanitized_key,
        '#attributes' => [
          'class' => ['btn', 'btn-danger', 'btn-sm', 'delete-element-button'],
          'onclick' => 'if(!confirm("Really Delete?")){return false;}',
        ],
        '#submit' => ['::deleteElementSubmit'],
        '#limit_validation_errors' => [],
        '#element_uri' => $key
      ];

      // DERIVE COMPONENT STEM BUTTON
      if ($this->element_type == 'componentstem') {
        $form['element_cards_wrapper'][$sanitized_key]['card']['footer']['actions']['ingest'] = [
          '#type' => 'submit',
          '#value' => $this->t('Derive New ' . $preferred_component),
          '#name' => 'derive_componentstem_' . $sanitized_key,
          '#attributes' => [
            'class' => ['btn', 'btn-success', 'btn-sm', 'derive-button'],
          ],
          '#submit' => ['::deriveComponentStemSubmit'],
          '#limit_validation_errors' => [],
          '#element_uri' => $key
        ];
      }
    }
  }

  /**
   * HANDLER FOR LOAD MORE BUTTON
   */
  public function loadMoreSubmit(array &$form, FormStateInterface $form_state)
  {
    // Atualiza o tamanho da página para carregar mais itens
    $current_page_size = $form_state->get('page_size') ?? 9;
    $form_state->set('previous_page_size', (int) $current_page_size);
    $pagesize = $current_page_size + 9; // Soma mais 9 ao tamanho atual
    $form_state->set('page_size', $pagesize);

    // \Drupal::logger('rep_select_mt_form')->notice('Load More Triggered: new page_size @page_size', [
    //     '@page_size' => $pagesize,
    // ]);

    // FORCE REBUILD
    $form_state->setRebuild();
  }

  /**
   * HANDLER TO CHANGE TO TABLE VIEW
   */
  public function viewTableSubmit(array &$form, FormStateInterface $form_state)
  {
    $form_state->set('view_type', 'table');
    $session = \Drupal::request()->getSession();
    $session->set('dpl_select_view_type', 'table');
    $form_state->setRebuild();
  }

  /**
   * HANDLER TO CHANGE TO CARD VIEW
   */
  public function viewCardSubmit(array &$form, FormStateInterface $form_state)
  {
    $form_state->set('view_type', 'card');
    $session = \Drupal::request()->getSession();
    $session->set('dpl_select_view_type', 'card');
    $form_state->setRebuild();
  }

  /**
   * HANDLER TO EDIT CARD
   */
  public function editElementSubmit(array &$form, FormStateInterface $form_state)
  {
    $triggering_element = $form_state->getTriggeringElement();
    $uri = $triggering_element['#element_uri'];

    $this->performEdit($uri, $form_state);
  }

  /**
   * HANDLER TO DELETE CARD
   */
  public function deleteElementSubmit(array &$form, FormStateInterface $form_state)
  {
    $triggering_element = $form_state->getTriggeringElement();
    $uri = $triggering_element['#element_uri'];

    $this->performDelete([$uri], $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // RETRIEVE TRIGGERING BUTTON
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'clear_filters') {
      $this->clearSavedFilters($form_state);
      return;
    }

    // SET USER ID AND PREVIOUS URL FOR TRACKING STORE URLS
    $uid = \Drupal::currentUser()->id();
    $previousUrl = \Drupal::request()->getRequestUri();

    // RETRIEVE SELECTED ROWS, IF ANY
    $selected_rows = $form_state->getValue('element_table');
    $rows = [];
    foreach ($selected_rows as $index => $selected) {
      if ($selected) {
        $rows[$index] = $index;
      }
    }

    // ADD ELEMENT
    if ($button_name === 'add_element') {
      if ($this->element_type == 'platform') {
        Utils::trackingStoreUrls($uid, $previousUrl, 'dpl.add_platform');
        $url = Url::fromRoute('dpl.add_platform');
      }
      if ($this->element_type == 'stream') {
        Utils::trackingStoreUrls($uid, $previousUrl, 'dpl.add_stream');
        $url = Url::fromRoute('dpl.add_stream');
      }
      if ($this->element_type == 'deployment') {
        Utils::trackingStoreUrls($uid, $previousUrl, 'dpl.add_deployment');
        $url = Url::fromRoute('dpl.add_deployment');
      }
      if ($this->element_type == 'platforminstance' ||
          $this->element_type == 'instrumentinstance' ||
          $this->element_type == 'componentinstance') {
        Utils::trackingStoreUrls($uid, $previousUrl, 'dpl.add_instance');
        $url = Url::fromRoute('dpl.add_instance');
        $url->setRouteParameter('elementtype', $this->element_type);
      }
      $form_state->setRedirectUrl($url);
    }

    // EDIT ELEMENT
    if ($button_name === 'edit_element') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addWarning(t("Select the exact " . $this->single_class_name . " to be edited."));
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addWarning(t("No more than one " . $this->single_class_name . " can be edited at once."));
      } else {
        $first = array_shift($rows);
        if ($this->element_type == 'platform') {
          Utils::trackingStoreUrls($uid, $previousUrl, 'dpl.edit_platform');
          $url = Url::fromRoute('dpl.edit_platform', ['platformuri' => base64_encode($first)]);
        }
        if ($this->element_type == 'stream') {
          Utils::trackingStoreUrls($uid, $previousUrl, 'dpl.edit_stream');
          $url = Url::fromRoute('dpl.edit_stream', ['streamuri' => base64_encode($first)]);
        }
        if ($this->element_type == 'deployment') {
          Utils::trackingStoreUrls($uid, $previousUrl, 'dpl.edit_deployment');
          $url = Url::fromRoute('dpl.edit_deployment', ['deploymenturi' => base64_encode($first)]);
        }
        if ($this->element_type == 'platforminstance' ||
            $this->element_type == 'instrumentinstance' ||
            $this->element_type == 'componentinstance') {
          Utils::trackingStoreUrls($uid, $previousUrl, 'dpl.edit_instance');
          $url = Url::fromRoute('dpl.edit_instance');
          $url->setRouteParameter('instanceuri', base64_encode($first));
        }
        $form_state->setRedirectUrl($url);
      }
    }

    // DELETE ELEMENT
    if ($button_name === 'delete_element') {
      if (sizeof($rows) <= 0) {
        \Drupal::messenger()->addWarning(t("At least one " . $this->single_class_name . " needs to be selected to be deleted."));
        return;
      } else {
        $api = \Drupal::service('rep.api_connector');
        foreach($rows as $shortUri) {
          $uri = Utils::plainUri($shortUri);
          $api->elementDel('platform',$uri);
        }
        \Drupal::messenger()->addMessage(t("Selected " . $this->plural_class_name . " has/have been deleted successfully."));
        return;
      }
    }

    // BACK TO LANDING PAGE
    if ($button_name === 'back') {
      $url = Url::fromRoute('rep.home');
      $form_state->setRedirectUrl($url);
      return;
    }

    return;

  }

  /**
   * EDIT CARD
   */
  protected function performEdit($uri, FormStateInterface $form_state)
  {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = \Drupal::request()->getRequestUri();

    // Rastreia a URL para fins de navegação
    Utils::trackingStoreUrls($uid, $previousUrl, 'dpl.edit_' . $this->element_type);

    // Defina o parâmetro correto com base no tipo de elemento
    $params = [];
    switch ($this->element_type) {
      case 'platform':
        $params = ['platformuri' => base64_encode($uri)];
        break;
      case 'stream':
        $params = ['streamuri' => base64_encode($uri)];
        break;
      case 'deployment':
        $params = ['deploymenturi' => base64_encode($uri)];
        break;
      default:
        $params = ['elementuri' => base64_encode($uri)];
        break;
    }

    // Define a URL de edição com o parâmetro correto
    $url = Url::fromRoute('dpl.edit_' . $this->element_type, $params);

    // Redireciona para a URL de edição
    $form_state->setRedirectUrl($url);
  }


  /**
   * DELETE CARD
   */
  protected function performDelete(array $uris, FormStateInterface $form_state)
  {
    $api = \Drupal::service('rep.api_connector');

    foreach ($uris as $uri) {
      // Obter o tipo de elemento para gerar a URL de exclusão correta
      $element_type_route = 'dpl.delete_' . $this->element_type;
      $params = [];

      switch ($this->element_type) {
        case 'platform':
          $params = ['platformuri' => base64_encode($uri)];
          break;
        case 'stream':
          $params = ['streamuri' => base64_encode($uri)];
          break;
        case 'deployment':
          $params = ['deploymenturi' => base64_encode($uri)];
          break;
        default:
          $params = ['elementuri' => base64_encode($uri)];
          break;
      }

      // Excluir o elemento usando o conector de API
      $api->elementDel($this->element_type, $uri);

      // Mensagem de confirmação de exclusão
      \Drupal::messenger()->addMessage($this->t('Item with URI %uri was deleted successfully.', ['%uri' => $uri]));
    }

    // Exibe uma mensagem geral de confirmação para os elementos selecionados
    \Drupal::messenger()->addMessage($this->t('The selected %elements were deleted successfully.', [
      '%elements' => $this->plural_class_name,
    ]));

    // Reconstrói o formulário para refletir a exclusão
    $form_state->setRebuild();
  }



}
