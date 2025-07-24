<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\ListManagerEmailPage;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Platform;
use Drupal\rep\Entity\Stream;
use Drupal\rep\Entity\Deployment;
use Drupal\rep\Entity\VSTOIInstance;

class DPLSelectForm extends FormBase {

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
    $this->manager_email = \Drupal::currentUser()->getEmail();
    $uid = \Drupal::currentUser()->id();
    $user = \Drupal\user\Entity\User::load($uid);
    $this->manager_name = $user->name->value;

    // GET ELEMENT TYPE
    $this->element_type = $elementtype;
    if ($this->element_type != NULL) {
      $this->setListSize(ListManagerEmailPage::total($this->element_type, $this->manager_email));
    }

    // SET PAGE_SIZE
    $pagesize = $form_state->get('page_size') ?? $pagesize ?? 9;
    $form_state->set('page_size', $pagesize);

    /// GET VIEW MODE
    $session = \Drupal::request()->getSession();
    $view_type = $form_state->get('view_type') ?? $session->get('dpl_select_view_type') ?? 'table';
    $form_state->set('view_type', $view_type);

    if ($view_type == 'table') {

      $this->setListSize(-1);
      if ($this->element_type != NULL) {
        $this->setListSize(ListManagerEmailPage::total($this->element_type, $this->manager_email));
      }
      if (gettype($this->list_size) == 'string') {
        $total_pages = "0";
      } else {
        if ($this->list_size % $pagesize == 0) {
          $total_pages = $this->list_size / $pagesize;
        } else {
          $total_pages = (int) floor($this->list_size / $pagesize) + 1;
        }
      }

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

      $this->setList(ListManagerEmailPage::exec($this->element_type, $this->manager_email, $page, $pagesize));
    } else {
      // SET PAGE_SIZE
      $pagesize = $form_state->get('page_size') ?? $pagesize ?? 9;
      $form_state->set('page_size', $pagesize);
      $this->setList(ListManagerEmailPage::exec($this->element_type, $this->manager_email, 1, $pagesize));
    }

    $this->single_class_name = "";
    $this->plural_class_name = "";

    $preferred_instrument = \Drupal::config('rep.settings')->get('preferred_instrument');
    $preferred_detector = \Drupal::config('rep.settings')->get('preferred_detector');
    $preferred_actuator = \Drupal::config('rep.settings')->get('preferred_actuator');

    switch ($this->element_type) {

      // PLATFORM
      case "platform":
        $this->single_class_name = "Platform";
        $this->plural_class_name = "Platforms";
        $header = Platform::generateHeader();
        $output = Platform::generateOutput($this->getList());
        $outputCard = Platform::generateCardOutput($this->getList());
        break;

      // PLATFORM INSTANCE
      case "platforminstance":
        $this->single_class_name = "Platform Instance";
        $this->plural_class_name = "Platform Instances";
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

      // DETECTOR INSTANCE
      case "detectorinstance":
        $this->single_class_name = $preferred_detector . " Instance";
        $this->plural_class_name = $preferred_detector . " Instances";
        $header = VSTOIInstance::generateHeader($this->element_type);
        $output = VSTOIInstance::generateOutput($this->element_type, $this->getList());
        $outputCard = VSTOIInstance::generateCardOutput($this->element_type, $this->getList());
        break;

      // ACTUATOR INSTANCE
      case "actuatorinstance":
        $this->single_class_name = $preferred_actuator . " Instance";
        $this->plural_class_name = $preferred_actuator . " Instances";
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
        'class' => ['table-view-button', 'fa-xl', 'mx-1'],
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
        'class' => ['card-view-button', 'fa-xl'],
        'title' => $this->t('Card View'),
      ],
      '#submit' => ['::viewCardSubmit'],
      '#limit_validation_errors' => [],
    ];

    $form['add_element'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add New ' . $this->single_class_name),
      '#name' => 'add_element',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'add-element-button'],
      ],
    ];

    // RENDER BASED ON VIEW TYPE
    if ($view_type == 'table') {
      $this->buildTableView($form, $form_state, $header, $output);

      $form['pager'] = [
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
      $this->buildCardView($form, $form_state, $header, $outputCard);

      $total_items = $this->getListSize();
      $current_page_size = $form_state->get('page_size') ?? 9;

      if ($total_items > $current_page_size) {
        $form['load_more'] = [
          '#type' => 'submit',
          '#value' => $this->t('Load More'),
          '#name' => 'load_more',
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'load-more-button'],
            'id' => 'load-more-button',
            'style' => 'display: none;',
          ],
          '#submit' => ['::loadMoreSubmit'],
          '#limit_validation_errors' => [],
        ];

        // ADD LOADING OVERLAY
        $form['loading_overlay'] = [
          '#type' => 'container',
          '#attributes' => [
            'id' => 'loading-overlay',
            'class' => ['loading-overlay'],
            'style' => 'display: none;',
          ],
          '#markup' => '<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>',
        ];

        $form['list_state'] = [
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
   * BUILD TABLE VIEW
   */
  protected function buildTableView(array &$form, FormStateInterface $form_state, $header, $output)
  {
    $preferred_detector = \Drupal::config('rep.settings')->get('preferred_detector');
    $preferred_actuator = \Drupal::config('rep.settings')->get('preferred_actuator');

    $form['edit_selected_element'] = [
      '#type' => 'submit',
      '#value' => $this->t('Edit ' . $this->single_class_name . ' Selected'),
      '#name' => 'edit_element',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'edit-element-button'],
      ],
    ];
    $form['delete_selected_element'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete ' . $this->plural_class_name . ' Selected'),
      '#name' => 'delete_element',
      '#attributes' => [
        'onclick' => 'if(!confirm("Really Delete?")){return false;}',
        'class' => ['btn', 'btn-primary', 'delete-element-button'],
      ],
    ];
    if ($this->element_type == 'detectorstem') {
      $form['derive_detectorstem'] = [
        '#type' => 'submit',
        '#value' => $this->t('Derive New ' . $preferred_detector. ' Stem from Selected'),
        '#name' => 'derive_detectorstem',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'derive-button'],
        ],
      ];
    }
    if ($this->element_type == 'actuatorstem') {
      $form['derive_actuatorstem'] = [
        '#type' => 'submit',
        '#value' => $this->t('Derive New ' . $preferred_actuator . ' Stem from Selected'),
        '#name' => 'derive_actuatorstem',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'derive-button'],
        ],
      ];
    }
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
    $preferred_detector = \Drupal::config('rep.settings')->get('preferred_detector');
    $preferred_actuator = \Drupal::config('rep.settings')->get('preferred_actuator');

    $form['element_cards_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'element-cards-wrapper', 'class' => ['row', 'mt-3']],
    ];

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

      // DERIVE DETECTOR STEM BUTTON
      if ($this->element_type == 'detectorstem') {
        $form['element_cards_wrapper'][$sanitized_key]['card']['footer']['actions']['ingest'] = [
          '#type' => 'submit',
          '#value' => $this->t('Derive New ' . $preferred_detector),
          '#name' => 'derive_detectorstem_' . $sanitized_key,
          '#attributes' => [
            'class' => ['btn', 'btn-success', 'btn-sm', 'derive-button'],
          ],
          '#submit' => ['::deriveDetectorStemSubmit'],
          '#limit_validation_errors' => [],
          '#element_uri' => $key
        ];
      }
      // DERIVE ACTUATOR BUTTON
      if ($this->element_type == 'actuator') {
        $form['element_cards_wrapper'][$sanitized_key]['card']['footer']['actions']['ingest'] = [
          '#type' => 'submit',
          '#value' => $this->t('Derive New ' . $preferred_actuator),
          '#name' => 'derive_actuator_' . $sanitized_key,
          '#attributes' => [
            'class' => ['btn', 'btn-success', 'btn-sm', 'derive-button'],
          ],
          '#submit' => ['::deriveActuatorSubmit'],
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
          $this->element_type == 'detectorinstance' ||
          $this->element_type == 'actuatorinstance') {
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
            $this->element_type == 'detectorinstance' ||
            $this->element_type == 'actuatorinstance') {
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
