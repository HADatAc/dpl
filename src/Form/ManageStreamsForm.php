<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\dpl\Form\ListStreamStatePage;
use Drupal\rep\Entity\Stream;
use Drupal\rep\ManageOwnerFilter;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\HASCO;
use Drupal\rep\Vocabulary\REPGUI;

class ManageStreamsForm extends FormBase {

  private const KEYWORD_SCAN_LIMIT = 9999;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dpl_manage_streams_form';
  }

  protected $manager_email;

  protected $manager_name;

  protected $state;

  protected $list;

  protected $list_size;

  protected $page_size;

  public function getManagerEmail() {
    return $this->manager_email;
  }
  public function setManagerEmail($manager_email) {
    return $this->manager_email = $manager_email;
  }

  public function getManagerName() {
    return $this->manager_name;
  }
  public function setManagerName($manager_name) {
    return $this->manager_name = $manager_name;
  }

  public function getState() {
    return $this->state;
  }
  public function setState($state) {
    return $this->state = $state;
  }

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

  public function getPageSize() {
    return $this->page_size;
  }
  public function setPageSize($page_size) {
    return $this->page_size = $page_size;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $state=NULL, $page=NULL, $pagesize=NULL) {

    $page = $page ?? 1;
    $pagesize = $pagesize ?? 10;

    // Attach custom library.
    $form['#attached']['library'][] = 'dpl/dpl_accordion';
    $form['#attached']['library'][] = 'dpl/dpl_manage_filters';

    // FIND Stream State Related to URL State
    switch ($state) {
      case 'active':
        $apiState = rawurlencode(HASCO::ACTIVE);
        break;
      case 'closed':
        $apiState = rawurlencode(HASCO::CLOSED);
        break;
      case 'design':
        $apiState = rawurlencode(HASCO::DRAFT);
        break;
      case 'all':
      default:
        $apiState = rawurlencode(HASCO::ALL_STATUSES);
        break;
    }

    // dpm($apiState, 'Debug API State', 'status', FALSE);

    // GET manager EMAIL
    $current_user = \Drupal::currentUser();
    $user = \Drupal::entityTypeManager()->getStorage('user')->load($current_user->id());
    $this->setManagerEmail($user->getEmail());
    $this->setManagerName($user->getAccountName());

    $session = \Drupal::request()->getSession();

    // Filters (persisted in session)
    $is_admin = ManageOwnerFilter::isAdmin();

    $text_filter = $form_state->getValue('text_filter');
    if ($text_filter === NULL) {
      $text_filter = $session->get('dpl_manage_streams_text_filter', '');
    }
    else {
      $session->set('dpl_manage_streams_text_filter', $text_filter);
    }
    $text_filter = trim((string) $text_filter);

    $manager_filter = '';
    if ($is_admin) {
      $manager_filter = $form_state->getValue('manager_filter');
      if ($manager_filter === NULL) {
        $manager_filter = $session->get('dpl_manage_streams_manager_filter', '');
      }
      else {
        $manager_filter = ManageOwnerFilter::normalizeSelectedEmail($manager_filter);
        $session->set('dpl_manage_streams_manager_filter', $manager_filter);
      }
    }
    else {
      $session->remove('dpl_manage_streams_manager_filter');
    }

    $effective_manager_email = $this->getManagerEmail();
    if ($is_admin && $manager_filter !== '') {
      $effective_manager_email = $manager_filter;
    }
    $form_state->set('effective_manager_email', $effective_manager_email);

    $has_active_filters = ($text_filter !== '') || ($is_admin && trim((string) $manager_filter) !== '');

    // View mode (table/card)
    $view_type = $form_state->get('view_type') ?? $session->get('dpl_select_view_type') ?? 'table';
    $form_state->set('view_type', $view_type);
    $table_active_class = ($view_type === 'table') ? ['selected-button'] : [];
    $card_active_class = ($view_type === 'card') ? ['selected-button'] : [];

    // GET TOTAL NUMBER OF ELEMENTS AND TOTAL NUMBER OF PAGES
    $this->setState($state);

    // FOR TESTING
    // $message = "HASCO: {$apiState}\nSTATE FORM: {$this->getState()}";
    // dpm($message, 'Debug HASCO', 'status', FALSE);
    // $apiState = $this->getState();

    $this->setPageSize($pagesize);
    $this->setListSize(-1);

    $api_total = 0;
    if ($this->getState() != NULL) {
      $api_total = ListStreamStatePage::total($apiState, $effective_manager_email);
    }

    $keyword_active = ($text_filter !== '');
    // Always initialize list size from API total so pagination works.
    $this->setListSize($api_total);

    // Compute total pages (at least 1)
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
      $next_page_link = ListStreamStatePage::link($this->getState(), $next_page, $pagesize);
    } else {
      $next_page_link = '';
    }
    if ($page > 1) {
      $previous_page = $page - 1;
      $previous_page_link = ListStreamStatePage::link($this->getState(), $previous_page, $pagesize);
    } else {
      $previous_page_link = '';
    }

    // RETRIEVE ELEMENTS
    if ($keyword_active) {
      $scan_limit = self::KEYWORD_SCAN_LIMIT;
      $fetch_size = $scan_limit;
      if (is_numeric($api_total)) {
        $fetch_size = min($scan_limit, max(0, (int) $api_total));
      }

      $all_streams = [];
      if ($fetch_size > 0) {
        $all_streams = ListStreamStatePage::exec($apiState, $effective_manager_email, 1, $fetch_size);
      }
      if (!is_array($all_streams)) {
        $all_streams = [];
      }

      $filtered = $this->filterStreamsByKeyword($all_streams, $text_filter);
      $filtered_total = count($filtered);
      $this->setListSize($filtered_total);

      // Recompute total pages based on filtered results.
      $total_pages = 1;
      if ($filtered_total > 0 && $pagesize > 0) {
        $total_pages = (int) ceil($filtered_total / $pagesize);
      }
      $page = max(1, min((int) $page, (int) $total_pages));

      $offset = ($page <= 1) ? 0 : (($page - 1) * $pagesize);
      $page_list = array_slice($filtered, $offset, $pagesize);
      $this->setList($page_list);

      // Update pager links based on filtered total_pages.
      if ($page < $total_pages) {
        $next_page = $page + 1;
        $next_page_link = ListStreamStatePage::link($this->getState(), $next_page, $pagesize);
      } else {
        $next_page_link = '';
      }
      if ($page > 1) {
        $previous_page = $page - 1;
        $previous_page_link = ListStreamStatePage::link($this->getState(), $previous_page, $pagesize);
      } else {
        $previous_page_link = '';
      }
    }
    else {
      $this->setList(ListStreamStatePage::exec($apiState, $effective_manager_email, $page, $pagesize));
    }

    //dpm($this->getList());
    $header = Stream::generateHeaderState($apiState);
    $output = Stream::generateOutputState($apiState, $this->getList());

    // PUT FORM TOGETHER
    $form['page_title'] = [
      '#type' => 'item',
      '#title' => $this->t('<h3>Manage Streams</h3>'),
    ];
    $form['page_subtitle'] = [
      '#type' => 'item',
      '#title' => $this->t('<h4>Streams maintained by <font color="DarkGreen">' . $this->getManagerName() . ' (' . $this->getManagerEmail() . ')</font></h4>'),
    ];

    // View toggle (Table / Card)
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

    $form['pills_card'] = [
      '#type' => 'markup',
      '#markup' => '
      <div class="card">
          <div class="card-header">
              <ul class="nav nav-pills nav-justified mb-0" id="pills-tab" role="tablist">
                  <li class="nav-item" role="presentation">
                      <a class="nav-link ' . ($state === 'design' ? 'active-dp2' : '') . '" id="pills-design-tab"  href="' .
                      $this->stateLink('design', $page, $pagesize) . '" role="tab">Upcoming Streams</a>
                  </li>
                  <li class="nav-item" role="presentation">
                      <a class="nav-link ' . ($state === 'active' ? 'active-dp2' : '') . '" id="pills-active-tab" href="' .
                      $this->stateLink('active', $page, $pagesize) . '" role="tab">Active Streams</a>
                  </li>
                  <li class="nav-item" role="presentation">
                      <a class="nav-link ' . ($state === 'closed' ? 'active-dp2' : '') . '" id="pills-closed-tab" href="' .
                      $this->stateLink('closed', $page, $pagesize) . '" role="tab">Completed Streams</a>
                  </li>
                  <li class="nav-item" role="presentation">
                      <a class="nav-link ' . ($state === 'all' ? 'active-dp2' : '') . '" id="pills-all-tab" href="' .
                      $this->stateLink('all', $page, $pagesize) . '" role="tab">All Streams</a>
                  </li>
              </ul>
          </div>
      </div>',
  ];

    $form['break_line'] = [
      '#type' => 'item',
      '#title' => $this->t('<BR>'),
    ];

    if ($this->getState() == 'active') {
      $form['break_line'] = [
        '#type' => 'item',
        '#title' => $this->t('<br><b>Note</b>: To create a new stream, select the option "Upcoming Streams" above.<br>'),
      ];
    }

    $form['card'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['card']],
      //'card_header' => [
      //  '#type' => 'container',
      //  '#attributes' => ['class' => ['card-header']],
      //],
      'card_body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-body'], 'id' => 'streams-card-body-wrapper'],
      ],
    ];

    $show_owner_indicator = $is_admin && $manager_filter !== '' && strcasecmp($effective_manager_email, $manager_filter) === 0;
    if ($show_owner_indicator) {
      $form['card']['card_body']['owner_indicator'] = [
        '#type' => 'item',
        '#markup' => $this->t('<div class="alert alert-info py-2 mb-3"><strong>A visualizar owner:</strong> @owner</div>', [
          '@owner' => $effective_manager_email,
        ]),
      ];
    }

    // Collapsed filters panel
    $form['card']['card_body']['filters_panel'] = [
      '#type' => 'details',
      '#title' => $this->t('Filter(s)'),
      '#open' => $has_active_filters,
      '#attributes' => [
        'class' => ['dpl-manage-filters-panel'],
      ],
    ];

    $form['card']['card_body']['filters_panel']['filter_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['row', 'g-2', 'align-items-end', 'dpl-manage-filters'],
      ],
    ];

    $form['card']['card_body']['filters_panel']['filter_container']['text_filter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Keyword'),
      '#title_display' => 'invisible',
      '#default_value' => $text_filter,
      '#prefix' => '<div class="col-12 col-lg-4">',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => '::ajaxReloadCardBody',
        'wrapper' => 'streams-card-body-wrapper',
        'event' => 'change',
      ],
      '#attributes' => [
        'class' => ['form-control'],
        'placeholder' => $this->t('Type in your search criteria'),
        'onkeydown' => 'if (event.keyCode == 13) { event.preventDefault(); this.blur(); }',
      ],
    ];

    if ($is_admin) {
      $form['card']['card_body']['filters_panel']['filter_container']['manager_filter'] = [
        '#type' => 'textfield',
        '#title' => $this->t('User'),
        '#title_display' => 'invisible',
        '#default_value' => $manager_filter,
        '#prefix' => '<div class="col-12 col-lg-4">',
        '#suffix' => '</div>',
        '#ajax' => [
          'callback' => '::ajaxReloadCardBody',
          'wrapper' => 'streams-card-body-wrapper',
          'event' => 'change',
        ],
        '#attributes' => [
          'class' => ['form-control'],
          'placeholder' => $this->t('User email (owner filter)'),
        ],
      ];
    }

    $form['card']['card_body']['filters_panel']['filter_container']['clear_filters'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear Filters'),
      '#name' => 'clear_filters',
      '#limit_validation_errors' => [],
      '#prefix' => '<div class="col-12 col-md-4 col-lg-2 d-grid">',
      '#suffix' => '</div>',
      '#attributes' => [
        'class' => ['btn', 'btn-outline-secondary'],
      ],
      '#ajax' => [
        'callback' => '::ajaxReloadCardBody',
        'wrapper' => 'streams-card-body-wrapper',
        'event' => 'click',
      ],
    ];

    $form['card']['card_body']['actions'] = [
      '#type' => 'actions',
      '#attributes' => [
        // Use Bootstrap's btn-group to keep buttons inline
        // and justify-content-start to align them to the left
        'class' => ['btn-group', 'justify-content-start', 'mb-3'],
        'style' => 'align-self: flex-start!important;',
        // If you don't have Bootstrap, you can force flex layout:
        // 'style' => 'display:flex; justify-content:flex-start;',
      ],
      '#weight' => -10,
    ];

    if ($view_type === 'table') {
      $form['card']['card_body']['actions']['add_element'] = [
        '#type' => 'submit',
        '#value' => $this->t('Create Stream'),
        '#name' => 'add_element',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'add-element-button', 'me-1'],
        ],
      ];

      if ($this->getState() == 'design') {
        $form['card']['card_body']['actions']['edit_selected_element'] = [
          '#type' => 'submit',
          '#value' => $this->t('Edit Selected'),
          '#name' => 'edit_element',
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'edit-element-button', 'ms-1'],
          ],
        ];
        $form['card']['card_body']['actions']['execute_selected_element'] = [
          '#type' => 'submit',
          '#value' => $this->t('Execute Selected'),
          '#name' => 'execute_element',
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'play-button', 'ms-1'],
          ],
        ];
        $form['card']['card_body']['actions']['delete_selected_element'] = [
          '#type' => 'submit',
          '#value' => $this->t('Delete Selected'),
          '#name' => 'delete_element',
          '#attributes' => [
            'onclick' => 'if(!confirm("Really Delete?")){return false;}',
            'class' => ['btn', 'btn-primary', 'delete-button', 'ms-1'],
          ],
        ];
      }

      if ($this->getState() == 'active') {
        $form['card']['card_body']['actions']['expose_selected'] = [
          '#type' => 'submit',
          '#value' => $this->t('Expose Selected'),
          '#name' => 'expose_element',
          '#disabled' => TRUE,
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'expose-button', 'me-1'],
          ],
        ];
        $form['card']['card_body']['actions']['close_selected'] = [
          '#type' => 'submit',
          '#value' => $this->t('Close Selected'),
          '#name' => 'close_element',
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'close-button'],
          ],
        ];
      }

      $form['card']['card_body']['element_table'] = [
        '#type' => 'tableselect',
        '#header' => $header,
        '#options' => $output,
        '#js_select' => FALSE,
        '#empty' => t('No stream has been found'),
      ];
    }
    else {
      $form['card']['card_body']['actions']['add_element'] = [
        '#type' => 'submit',
        '#value' => $this->t('Create Stream'),
        '#name' => 'add_element',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'add-element-button', 'me-1'],
        ],
      ];

      $placeholder_image = base_path() . \Drupal::service('extension.list.module')->getPath('rep') . '/images/placeholders/message_stream_placeholder.png';

      $streams_by_uri = [];
      if (is_array($this->getList())) {
        foreach ($this->getList() as $stream) {
          if (is_object($stream) && !empty($stream->uri)) {
            $streams_by_uri[$stream->uri] = $stream;
          }
        }
      }

      $form['card']['card_body']['element_cards_wrapper'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['row', 'mt-3']],
      ];

      foreach ($output as $uri => $row) {
        $safe_key = md5($uri);

        $stream_obj = $streams_by_uri[$uri] ?? NULL;
        $header_text = '';
        if ($stream_obj && !empty($stream_obj->label)) {
          $header_text = (string) $stream_obj->label;
        }
        if ($header_text === '') {
          $header_text = strip_tags($row['element_uri'] ?? $uri);
        }

        $image_uri = $placeholder_image;
        if ($stream_obj && !empty($stream_obj->uri)) {
          $image_uri = Utils::getAPIImage($stream_obj->uri, $stream_obj->hasImageUri ?? NULL, $placeholder_image);
        }

        $content = '';
        foreach ($header as $column_key => $column_label) {
          $label = (string) $column_label;
          $value = $row[$column_key] ?? '';
          $content .= '<p class="mb-0 pb-0"><strong>' . $label . ':</strong> ' . $value . '</p>';
        }

        $form['card']['card_body']['element_cards_wrapper'][$safe_key] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-md-4', 'mt-3']],
        ];

        $form['card']['card_body']['element_cards_wrapper'][$safe_key]['card'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['card', 'mb-4']],
        ];

        $form['card']['card_body']['element_cards_wrapper'][$safe_key]['card']['header'] = [
          '#type' => 'container',
          '#attributes' => [
            'style' => 'margin-bottom:0!important;',
            'class' => ['card-header'],
          ],
          '#markup' => '<h5 class="mb-0">' . $header_text . '</h5>',
        ];

        $form['card']['card_body']['element_cards_wrapper'][$safe_key]['card']['content_wrapper'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['row']],
        ];

        $form['card']['card_body']['element_cards_wrapper'][$safe_key]['card']['content_wrapper']['image'] = [
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
            ],
          ],
        ];

        $form['card']['card_body']['element_cards_wrapper'][$safe_key]['card']['content_wrapper']['content'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['col-md-7', 'card-body', 'justify-content-center'],
            'style' => 'margin-bottom:0!important;',
          ],
          'text' => [
            '#markup' => $content,
          ],
        ];

        $form['card']['card_body']['element_cards_wrapper'][$safe_key]['card']['footer'] = [
          '#type' => 'container',
          '#attributes' => [
            'style' => 'margin-bottom:0!important;',
            'class' => ['d-flex', 'card-footer', 'justify-content-end'],
          ],
        ];

        $form['card']['card_body']['element_cards_wrapper'][$safe_key]['card']['footer']['actions'] = [
          '#type' => 'actions',
          '#attributes' => [
            'style' => 'margin-bottom:0!important;',
            'class' => ['mb-0'],
          ],
        ];

        if ($this->getState() == 'design') {
          $form['card']['card_body']['element_cards_wrapper'][$safe_key]['card']['footer']['actions']['edit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Edit'),
            '#name' => 'edit_element_' . $safe_key,
            '#attributes' => [
              'class' => ['btn', 'btn-primary', 'btn-sm', 'edit-element-button', 'me-1'],
            ],
            '#submit' => ['::editStreamSubmit'],
            '#limit_validation_errors' => [],
            '#stream_uri' => $uri,
          ];

          $form['card']['card_body']['element_cards_wrapper'][$safe_key]['card']['footer']['actions']['execute'] = [
            '#type' => 'submit',
            '#value' => $this->t('Execute'),
            '#name' => 'execute_element_' . $safe_key,
            '#attributes' => [
              'class' => ['btn', 'btn-primary', 'btn-sm', 'play-button', 'me-1'],
            ],
            '#submit' => ['::executeStreamSubmit'],
            '#limit_validation_errors' => [],
            '#stream_uri' => $uri,
          ];

          $form['card']['card_body']['element_cards_wrapper'][$safe_key]['card']['footer']['actions']['delete'] = [
            '#type' => 'submit',
            '#value' => $this->t('Delete'),
            '#name' => 'delete_element_' . $safe_key,
            '#attributes' => [
              'class' => ['btn', 'btn-danger', 'btn-sm', 'delete-button'],
              'onclick' => 'if(!confirm("Really Delete?")){return false;}',
            ],
            '#submit' => ['::deleteStreamSubmit'],
            '#limit_validation_errors' => [],
            '#stream_uri' => $uri,
          ];
        }

        if ($this->getState() == 'active') {
          $form['card']['card_body']['element_cards_wrapper'][$safe_key]['card']['footer']['actions']['close'] = [
            '#type' => 'submit',
            '#value' => $this->t('Close'),
            '#name' => 'close_element_' . $safe_key,
            '#attributes' => [
              'class' => ['btn', 'btn-primary', 'btn-sm', 'close-button'],
            ],
            '#submit' => ['::closeStreamSubmit'],
            '#limit_validation_errors' => [],
            '#stream_uri' => $uri,
          ];
        }
      }
    }
    $form['card']['card_body']['pager'] = [
      '#theme' => 'list-page',
      '#items' => [
        'page' => strval($page),
        'first' => ListStreamStatePage::link($this->getState(), 1, $pagesize),
        'last' => ListStreamStatePage::link($this->getState(), $total_pages, $pagesize),
        'previous' => $previous_page_link,
        'next' => $next_page_link,
        'last_page' => strval($total_pages),
        'links' => null,
        'title' => ' ',
      ],
    ];
    $form['space1'] = [
      '#type' => 'item',
      '#value' => $this->t('<br><br>'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#name' => 'back',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'back-button'],
      ],
    ];
    $form['space2'] = [
      '#type' => 'item',
      '#value' => $this->t('<br><br><br>'),
    ];

    return $form;
  }

  /**
   * Toggle to Table view.
   */
  public function viewTableSubmit(array &$form, FormStateInterface $form_state) {
    $form_state->set('view_type', 'table');
    $session = \Drupal::request()->getSession();
    $session->set('dpl_select_view_type', 'table');
    $form_state->setRebuild();
  }

  /**
   * Toggle to Card view.
   */
  public function viewCardSubmit(array &$form, FormStateInterface $form_state) {
    $form_state->set('view_type', 'card');
    $session = \Drupal::request()->getSession();
    $session->set('dpl_select_view_type', 'card');
    $form_state->setRebuild();
  }

  /**
   * Per-card action: Edit.
   */
  public function editStreamSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $stream_uri = $triggering_element['#stream_uri'] ?? NULL;
    if (empty($stream_uri)) {
      \Drupal::messenger()->addError($this->t('Missing stream URI.'));
      return;
    }

    $uid = \Drupal::currentUser()->id();
    $previousUrl = \Drupal::request()->getRequestUri();
    Utils::trackingStoreUrls($uid, $previousUrl, 'dpl.edit_stream');

    $form_state->setRedirect('dpl.edit_stream', ['streamuri' => base64_encode($stream_uri)]);
  }

  /**
   * Per-card action: Execute.
   */
  public function executeStreamSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $stream_uri = $triggering_element['#stream_uri'] ?? NULL;
    if (empty($stream_uri)) {
      \Drupal::messenger()->addError($this->t('Missing stream URI.'));
      return;
    }

    $uid = \Drupal::currentUser()->id();
    $previousUrl = \Drupal::request()->getRequestUri();
    Utils::trackingStoreUrls($uid, $previousUrl, 'dpl.execute_close_stream');

    $form_state->setRedirect('dpl.execute_close_stream', [
      'mode' => 'execute',
      'streamuri' => base64_encode($stream_uri),
    ]);
  }

  /**
   * Per-card action: Close.
   */
  public function closeStreamSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $stream_uri = $triggering_element['#stream_uri'] ?? NULL;
    if (empty($stream_uri)) {
      \Drupal::messenger()->addError($this->t('Missing stream URI.'));
      return;
    }

    $uid = \Drupal::currentUser()->id();
    $previousUrl = \Drupal::request()->getRequestUri();
    Utils::trackingStoreUrls($uid, $previousUrl, 'dpl.execute_close_stream');

    $form_state->setRedirect('dpl.execute_close_stream', [
      'mode' => 'close',
      'streamuri' => base64_encode($stream_uri),
    ]);
  }

  /**
   * Per-card action: Delete.
   */
  public function deleteStreamSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $stream_uri = $triggering_element['#stream_uri'] ?? NULL;
    if (empty($stream_uri)) {
      \Drupal::messenger()->addError($this->t('Missing stream URI.'));
      return;
    }

    $api = \Drupal::service('rep.api_connector');
    $api->elementDel('stream', Utils::plainUri($stream_uri));
    \Drupal::messenger()->addMessage($this->t('Selected stream has been deleted successfully.'));

    $form_state->setRebuild();
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
    if (!is_array($selected_rows)) {
      $selected_rows = [];
    }
    $rows = [];
    foreach ($selected_rows as $index => $selected) {
      if ($selected) {
        $rows[$index] = $index;
      }
    }

    // DESIGN STATE
    if ($button_name === 'design_state' && $this->getState() != 'design') {
      $url = Url::fromRoute('dpl.manage_streams_route');
      $url->setRouteParameter('state', 'design');
      $url->setRouteParameter('page', '1');
      $url->setRouteParameter('pagesize', $this->getPageSize());
      $form_state->setRedirectUrl($url);
      return;
    }

    // ACTIVE STATE
    if ($button_name === 'active_state' && $this->getState() != 'active') {
      $url = Url::fromRoute('dpl.manage_streams_route');
      $url->setRouteParameter('state', 'active');
      $url->setRouteParameter('page', '1');
      $url->setRouteParameter('pagesize', $this->getPageSize());
      $form_state->setRedirectUrl($url);
      return;
    }

    // CLOSED STATE
    if ($button_name === 'closed_state' && $this->getState() != 'closed') {
      $url = Url::fromRoute('dpl.manage_streams_route');
      $url->setRouteParameter('state', 'closed');
      $url->setRouteParameter('page', '1');
      $url->setRouteParameter('pagesize', $this->getPageSize());
      $form_state->setRedirectUrl($url);
      return;
    }

    // ALL STATE
    if ($button_name === 'all_state' && $this->getState() != 'all') {
      $url = Url::fromRoute('dpl.manage_streams_route');
      $url->setRouteParameter('state', 'all');
      $url->setRouteParameter('page', '1');
      $url->setRouteParameter('pagesize', $this->getPageSize());
      $form_state->setRedirectUrl($url);
      return;
    }

    // ADD ELEMENT
    if ($button_name === 'add_element') {
      Utils::trackingStoreUrls($uid, $previousUrl, 'dpl.add_stream');
      $url = Url::fromRoute('dpl.add_stream');
      $form_state->setRedirectUrl($url);
    }

    // EDIT ELEMENT
    if ($button_name === 'edit_element') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addWarning(t("Select the exact stream to be edited."));
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addWarning(t("No more than one stream can be edited at once."));
      } else {
        $first = array_shift($rows);
        Utils::trackingStoreUrls($uid, $previousUrl, 'dpl.edit_stream');
        $url = Url::fromRoute('dpl.edit_stream', ['streamuri' => base64_encode($first)]);
        $form_state->setRedirectUrl($url);
      }
    }

    // EXECUTE ELEMENT
    if ($button_name === 'execute_element') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addWarning(t("Select the exact stream to be executed."));
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addWarning(t("No more than one stream can be executed at once."));
      } else {
        $first = array_shift($rows);
        Utils::trackingStoreUrls($uid, $previousUrl, 'dpl.execute_close_stream');
        $url = Url::fromRoute('dpl.execute_close_stream', [
          'mode' => 'execute',
          'streamuri' => base64_encode($first)
        ]);
        $form_state->setRedirectUrl($url);
      }
    }

    // CLOSE ELEMENT
    if ($button_name === 'close_element') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addWarning(t("Select the exact stream to be closed."));
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addWarning(t("No more than one stream can be closed at once."));
      } else {
        $first = array_shift($rows);
        Utils::trackingStoreUrls($uid, $previousUrl, 'dpl.execute_close_stream');
        $url = Url::fromRoute('dpl.execute_close_stream', [
          'mode' => 'close',
          'streamuri' => base64_encode($first)
        ]);
        $form_state->setRedirectUrl($url);
      }
    }

    // EXPOSE ELEMENT
    if ($button_name === 'expose_element') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addWarning(t("Select the exact stream to be exposed."));
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addWarning(t("No more than one stream can be exposed at once."));
      } else {
        $first = array_shift($rows);
        Utils::trackingStoreUrls($uid, $previousUrl, 'dpl.execute_expose_stream');
        $url = Url::fromRoute('dpl.execute_expose_stream', [
          'mode' => 'expose',
          'streamuri' => base64_encode($first)
        ]);
        $form_state->setRedirectUrl($url);
      }
    }

    // DELETE ELEMENT
    if ($button_name === 'delete_element') {
      if (sizeof($rows) <= 0) {
        \Drupal::messenger()->addWarning(t("At least one stream needs to be selected to be deleted."));
        return;
      } else {
        $api = \Drupal::service('rep.api_connector');
        foreach($rows as $shortUri) {
          $uri = Utils::plainUri($shortUri);
          $api->elementDel('stream',$uri);
        }
        \Drupal::messenger()->addMessage(t("Selected stream(s) has/have been deleted successfully."));
        return;
      }
    }

    // MODIFY ELEMENT
    if ($button_name === 'modify_element') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addWarning(t("Select the exact stream to be modified."));
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addWarning(t("No more than one stream can be modified at once."));
      } else {
        $first = array_shift($rows);
        Utils::trackingStoreUrls($uid, $previousUrl, 'dpl.edit_stream');
        $url = Url::fromRoute('dpl.edit_stream', [
          'streamuri' => base64_encode($first)
        ]);
        $form_state->setRedirectUrl($url);
      }
    }

    // BACK TO LANDING PAGE
    if ($button_name === 'back') {
      $this->backUrl();
      return;
    }

    return;
  }

  /**
   * AJAX callback to reload the card body wrapper when filters change.
   */
  public function ajaxReloadCardBody(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    return $form['card']['card_body'];
  }

  /**
   * Clear persisted filters for Manage Streams.
   */
  protected function clearSavedFilters(FormStateInterface $form_state): void {
    $session = \Drupal::request()->getSession();
    $session->remove('dpl_manage_streams_text_filter');
    $session->remove('dpl_manage_streams_manager_filter');

    $input = $form_state->getUserInput();
    unset($input['text_filter'], $input['manager_filter']);
    $form_state->setUserInput($input);

    $form_state->setValue('text_filter', '');
    $form_state->setValue('manager_filter', '');
    $form_state->setRebuild(TRUE);
  }

  /**
   * In-memory keyword filter for streams.
   */
  protected function filterStreamsByKeyword(array $streams, string $keyword): array {
    $needle = trim($keyword);
    if ($needle === '') {
      return $streams;
    }

    $needle = function_exists('mb_strtolower') ? mb_strtolower($needle) : strtolower($needle);
    $filtered = [];

    foreach ($streams as $stream) {
      if (!is_object($stream)) {
        continue;
      }

      $parts = [];
      foreach (['uri', 'label', 'designedAt', 'startedAt', 'endedAt', 'method', 'messageProtocol', 'messageIP', 'messagePort'] as $prop) {
        if (isset($stream->{$prop}) && $stream->{$prop} !== NULL) {
          $parts[] = (string) $stream->{$prop};
        }
      }

      if (isset($stream->deployment)) {
        if (isset($stream->deployment->label)) {
          $parts[] = (string) $stream->deployment->label;
        }
        if (isset($stream->deployment->uri)) {
          $parts[] = (string) $stream->deployment->uri;
        }
      }

      if (isset($stream->study)) {
        if (isset($stream->study->label)) {
          $parts[] = (string) $stream->study->label;
        }
        if (isset($stream->study->uri)) {
          $parts[] = (string) $stream->study->uri;
        }
      }

      if (isset($stream->semanticDataDictionary)) {
        if (isset($stream->semanticDataDictionary->label)) {
          $parts[] = (string) $stream->semanticDataDictionary->label;
        }
        if (isset($stream->semanticDataDictionary->uri)) {
          $parts[] = (string) $stream->semanticDataDictionary->uri;
        }
      }

      $haystack = implode(' ', $parts);
      $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack) : strtolower($haystack);

      if ($haystack !== '' && strpos($haystack, $needle) !== FALSE) {
        $filtered[] = $stream;
      }
    }

    return $filtered;
  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'dpl.manage_streams_route');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

  public function stateLink($state, $page, $pagesize) {
    $root_url = \Drupal::request()->getBaseUrl();
    return $root_url . REPGUI::MANAGE_STREAMS .
        $state . '/' .
        strval($page) . '/' .
        strval($pagesize);
  }

}
