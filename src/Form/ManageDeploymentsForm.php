<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\dpl\Form\ListDeploymentStatePage;
use Drupal\rep\Entity\Deployment;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\REPGUI;

class ManageDeploymentsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dpl_manage_deployments_form';
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

    // Attach custom library.
    $form['#attached']['library'][] = 'dpl/dpl_accordion';

    // GET manager EMAIL
    $current_user = \Drupal::currentUser();
    $user = \Drupal::entityTypeManager()->getStorage('user')->load($current_user->id());
    $this->setManagerEmail($user->getEmail());
    $this->setManagerName($user->getAccountName());

    // GET TOTAL NUMBER OF ELEMENTS AND TOTAL NUMBER OF PAGES
    $this->setState($state);
    $this->setPageSize($pagesize);
    $this->setListSize(-1);
    if ($this->getState() != NULL) {
      $this->setListSize(ListDeploymentStatePage::total($this->getState(), $this->getManagerEmail()));
    }
    if (gettype($this->list_size) == 'string') {
      $total_pages = "0";
    } else {
      if ($this->list_size % $pagesize == 0) {
        $total_pages = $this->list_size / $pagesize;
      } else {
        $total_pages = floor($this->list_size / $pagesize) + 1;
      }
    }

    // CREATE LINK FOR NEXT PAGE AND PREVIOUS PAGE
    if ($page < $total_pages) {
      $next_page = $page + 1;
      $next_page_link = ListDeploymentStatePage::link($this->getState(), $this->getManagerEmail(), $next_page, $pagesize);
    } else {
      $next_page_link = '';
    }
    if ($page > 1) {
      $previous_page = $page - 1;
      $previous_page_link = ListDeploymentStatePage::link($this->getState(), $this->getManagerEmail(), $previous_page, $pagesize);
    } else {
      $previous_page_link = '';
    }

    // RETRIEVE ELEMENTS
    $this->setList(ListDeploymentStatePage::exec($this->getState(), $this->getManagerEmail(), $page, $pagesize));

    //dpm($this->getList());
    $header = Deployment::generateHeaderState($this->getState());
    $output = Deployment::generateOutputState($this->getState(), $this->getList());

    // PUT FORM TOGETHER
    $form['page_title'] = [
      '#type' => 'item',
      '#title' => $this->t('<h3 class="mt-5">Manage Deployments</h3>'),
    ];
    $form['page_subtitle'] = [
      '#type' => 'item',
      '#title' => $this->t('<h4>Deployments maintained by <font color="DarkGreen">' . $this->getManagerName() . ' (' . $this->getManagerEmail() . ')</font></h4>'),
    ];

    $form['pills_card'] = [
      '#type' => 'markup',
      '#markup' => '
      <div class="card">
          <div class="card-header">
              <ul class="nav nav-pills nav-justified mb-0" id="pills-tab" role="tablist">
                  <li class="nav-item" role="presentation">
                      <a class="nav-link ' . ($state === 'design' ? 'active' : '') . '" id="pills-design-tab"  href="' .
                      $this->stateLink('design',$page,$pagesize) . '" role="tab">Upcoming Deployments</a>
                  </li>
                  <li class="nav-item" role="presentation">
                      <a class="nav-link ' . ($state === 'active' ? 'active' : '') . '" id="pills-active-tab" href="' .
                      $this->stateLink('active',$page,$pagesize) . '" role="tab">Active Deployments</a>
                  </li>
                  <li class="nav-item" role="presentation">
                      <a class="nav-link ' . ($state === 'closed' ? 'active' : '') . '" id="pills-closed-tab" href="' .
                      $this->stateLink('closed',$page,$pagesize) . '" role="tab">Completed Deployments</a>
                  </li>
                  <li class="nav-item" role="presentation">
                      <a class="nav-link ' . ($state === 'all' ? 'active' : '') . '" id="pills-all-tab" href="' .
                      $this->stateLink('all',$page,$pagesize) . '" role="tab">All Deployments</a>
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
        '#title' => $this->t('<br><b>Note</b>: To create a new deployment, select the option "Upcoming Deployments" above.<br>'),
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
        '#attributes' => ['class' => ['card-body']],
      ],
    ];


    if ($this->getState() == 'design') {
      $form['card']['card_body']['add_element'] = [
        '#type' => 'submit',
        '#value' => $this->t('Create Deployment'),
        '#name' => 'add_element',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'add-element-button'],
        ],
      ];
      $form['card']['card_body']['edit_selected_element'] = [
        '#type' => 'submit',
        '#value' => $this->t('Edit Selected'),
        '#name' => 'edit_element',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'edit-element-button'],
        ],
      ];
      $form['card']['card_body']['execute_selected_element'] = [
        '#type' => 'submit',
        '#value' => $this->t('Execute Selected'),
        '#name' => 'execute_element',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'play-button'],
        ],
      ];
      $form['card']['card_body']['delete_selected_element'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete Selected'),
        '#name' => 'delete_element',
        '#attributes' => [
          'onclick' => 'if(!confirm("Really Delete?")){return false;}',
          'class' => ['btn', 'btn-primary', 'delete-element-button']
        ],
      ];
    }
    if ($this->getState() == 'active') {
      $form['card']['card_body']['close_selected'] = [
        '#type' => 'submit',
        '#value' => $this->t('Close Selected'),
        '#name' => 'close_element',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'close-button'],
        ],
      ];
      $form['card']['card_body']['modify_selected'] = [
        '#type' => 'submit',
        '#value' => $this->t('Modify Selected'),
        '#name' => 'modify_element',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'edit-element-button'],
        ],
      ];
      $form['card']['card_body']['stream_selected'] = [
        '#type' => 'submit',
        '#value' => $this->t('Streams of Selected'),
        '#name' => 'manage_streams',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'stream-button'],
        ],
      ];
    }
    $form['card']['card_body']['element_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $output,
      '#js_select' => FALSE,
      '#empty' => t('No deployment has been found'),
    ];
    $form['card']['card_body']['pager'] = [
      '#theme' => 'list-page',
      '#items' => [
        'page' => strval($page),
        'first' => ListDeploymentStatePage::link($this->getState(), $this->getManagerEmail(), 1, $pagesize),
        'last' => ListDeploymentStatePage::link($this->getState(), $this->getManagerEmail(), $total_pages, $pagesize),
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

    // DESIGN STATE
    if ($button_name === 'design_state' && $this->getState() != 'design') {
      $url = Url::fromRoute('dpl.manage_deployments_route');
      $url->setRouteParameter('state', 'design');
      $url->setRouteParameter('page', '1');
      $url->setRouteParameter('pagesize', $this->getPageSize());
      $form_state->setRedirectUrl($url);
      return;
    }

    // ACTIVE STATE
    if ($button_name === 'active_state' && $this->getState() != 'active') {
      $url = Url::fromRoute('dpl.manage_deployments_route');
      $url->setRouteParameter('state', 'active');
      $url->setRouteParameter('page', '1');
      $url->setRouteParameter('pagesize', $this->getPageSize());
      $form_state->setRedirectUrl($url);
      return;
    }

    // CLOSED STATE
    if ($button_name === 'closed_state' && $this->getState() != 'closed') {
      $url = Url::fromRoute('dpl.manage_deployments_route');
      $url->setRouteParameter('state', 'closed');
      $url->setRouteParameter('page', '1');
      $url->setRouteParameter('pagesize', $this->getPageSize());
      $form_state->setRedirectUrl($url);
      return;
    }

    // ALL STATE
    if ($button_name === 'all_state' && $this->getState() != 'all') {
      $url = Url::fromRoute('dpl.manage_deployments_route');
      $url->setRouteParameter('state', 'all');
      $url->setRouteParameter('page', '1');
      $url->setRouteParameter('pagesize', $this->getPageSize());
      $form_state->setRedirectUrl($url);
      return;
    }

    // ADD ELEMENT
    if ($button_name === 'add_element') {
      Utils::trackingStoreUrls($uid, $previousUrl, 'dpl.add_deployment');
      $url = Url::fromRoute('dpl.add_deployment');
      $form_state->setRedirectUrl($url);
    }

    // EDIT ELEMENT
    if ($button_name === 'edit_element') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addWarning(t("Select the exact deployment to be edited."));
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addWarning(t("No more than one deployment can be edited at once."));
      } else {
        $first = array_shift($rows);
        Utils::trackingStoreUrls($uid, $previousUrl, 'dpl.edit_deployment');
        $url = Url::fromRoute('dpl.edit_deployment', ['deploymenturi' => base64_encode($first)]);
        $form_state->setRedirectUrl($url);
      }
    }

    // EXECUTE ELEMENT
    if ($button_name === 'execute_element') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addWarning(t("Select the exact deployment to be executed."));
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addWarning(t("No more than one deployment can be executed at once."));
      } else {
        $first = array_shift($rows);
        Utils::trackingStoreUrls($uid, $previousUrl, 'dpl.execute_close_deployment');
        $url = Url::fromRoute('dpl.execute_close_deployment', [
          'mode' => 'execute',
          'deploymenturi' => base64_encode($first)
        ]);
        $form_state->setRedirectUrl($url);
      }
    }

    // CLOSE ELEMENT
    if ($button_name === 'close_element') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addWarning(t("Select the exact deployment to be closed."));
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addWarning(t("No more than one deployment can be closed at once."));
      } else {
        $first = array_shift($rows);
        Utils::trackingStoreUrls($uid, $previousUrl, 'dpl.execute_close_deployment');
        $url = Url::fromRoute('dpl.execute_close_deployment', [
          'mode' => 'close',
          'deploymenturi' => base64_encode($first)
        ]);
        $form_state->setRedirectUrl($url);
      }
    }

    // MANAGE STREAM
    if ($button_name === 'manage_streams') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addWarning(t("Select the exact deployment to have streams managed."));
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addWarning(t("To manage streams, select exactly one deployment."));
      } else {
        $first = array_shift($rows);
        Utils::trackingStoreUrls($uid, $previousUrl, 'dpl.manage_streams_route');
        $url = Url::fromRoute('dpl.manage_streams_route', [
          'deploymenturi' => base64_encode($first),
          'state' => 'active',
          'page' => 1,
          'pagesize' => 10,
        ]);
        $form_state->setRedirectUrl($url);
      }
    }

    // DELETE ELEMENT
    if ($button_name === 'delete_element') {
      if (sizeof($rows) <= 0) {
        \Drupal::messenger()->addWarning(t("At least one deployment needs to be selected to be deleted."));
        return;
      } else {
        $api = \Drupal::service('rep.api_connector');
        foreach($rows as $shortUri) {
          $uri = Utils::plainUri($shortUri);
          $api->elementDel('deployment',$uri);
        }
        \Drupal::messenger()->addMessage(t("Selected deployment(s) has/have been deleted successfully."));
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

  public function stateLink($state, $page, $pagesize) {
    $root_url = \Drupal::request()->getBaseUrl();
    return $root_url . REPGUI::MANAGE_DEPLOYMENTS .
        $state . '/' .
        strval($page) . '/' .
        strval($pagesize);
  }


}
