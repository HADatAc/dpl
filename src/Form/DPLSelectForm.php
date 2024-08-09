<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\rep\ListManagerEmailPage;
use Drupal\rep\Utils;
use Drupal\dpl\Entity\Platform;
use Drupal\dpl\Entity\PlatformInstance;
use Drupal\dpl\Entity\InstrumentInstance;
use Drupal\dpl\Entity\DetectorInstance;
use Drupal\dpl\Entity\Stream;
use Drupal\dpl\Entity\Deployment;

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
  public function buildForm(array $form, FormStateInterface $form_state, $elementtype=NULL, $page=NULL, $pagesize=NULL) {

    // GET manager EMAIL
    $this->manager_email = \Drupal::currentUser()->getEmail();
    $uid = \Drupal::currentUser()->id();
    $user = \Drupal\user\Entity\User::load($uid);
    $this->manager_name = $user->name->value;

    // GET TOTAL NUMBER OF ELEMENTS AND TOTAL NUMBER OF PAGES
    $this->element_type = $elementtype;
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
        $total_pages = floor($this->list_size / $pagesize) + 1;
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

    // RETRIEVE ELEMENTS
    $this->setList(ListManagerEmailPage::exec($this->element_type, $this->manager_email, $page, $pagesize));

    //dpm($this->getList());

    $this->single_class_name = "";
    $this->plural_class_name = "";

    $preferred_instrument = \Drupal::config('rep.settings')->get('preferred_instrument');
    $preferred_detector = \Drupal::config('rep.settings')->get('preferred_detector');

    switch ($this->element_type) {

      // PLATFORM
      case "platform":
        $this->single_class_name = "Platform";
        $this->plural_class_name = "Platforms";
        $header = Platform::generateHeader();
        $output = Platform::generateOutput($this->getList());    
        break;

      // PLATFORM INSTANCE
      case "platforminstance":
        $this->single_class_name = "Platform Instance";
        $this->plural_class_name = "Platform Instances";
        $header = PlatformInstance::generateHeader();
        $output = PlatformInstance::generateOutput($this->getList());    
        break;

      // INSTRUMENT INSTANCE
      case "instrumentinstance":
        $this->single_class_name = $preferred_instrument . " Instance";
        $this->plural_class_name = $preferred_instrument . " Instances";
        $header = InstrumentInstance::generateHeader();
        $output = InstrumentInstance::generateOutput($this->getList());    
        break;

      // DETECTOR INSTANCE
      case "detectorinstance":
        $this->single_class_name = $preferred_detector . " Instance";
        $this->plural_class_name = $preferred_detector . " Instances";
        $header = DetectorInstance::generateHeader();
        $output = DetectorInstance::generateOutput($this->getList());    
        break;

      // STREAM
      case "stream":
        $this->single_class_name = "Stream";
        $this->plural_class_name = "Streams";
        $header = Stream::generateHeader();
        $output = Stream::generateOutput($this->getList());    
        break;

      // DEPLOYMENT
      case "deployment":
        $this->single_class_name = "Deployment";
        $this->plural_class_name = "Deployments";
        $header = Deployment::generateHeader();
        $output = Deployment::generateOutput($this->getList());    
        break;

      default:
        $this->single_class_name = "Object of Unknown Type";
        $this->plural_class_name = "Objects of Unknown Types";
    }

    // PUT FORM TOGETHER
    $form['page_title'] = [
      '#type' => 'item',
      '#title' => $this->t('<h3>Manage ' . $this->plural_class_name . '</h3>'),
    ];
    $form['page_subtitle'] = [
      '#type' => 'item',
      '#title' => $this->t('<h4>' . $this->plural_class_name . ' maintained by <font color="DarkGreen">' . $this->manager_name . ' (' . $this->manager_email . ')</font></h4>'),
    ];
    $form['add_element'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add New ' . $this->single_class_name),
      '#name' => 'add_element',
    ];
    if ($this->element_type == 'detectorstem') {
      $form['derive_detectorstem'] = [
        '#type' => 'submit',
        '#value' => $this->t('Derive New ' . $preferred_detector. ' Stem from Selected'),
        '#name' => 'derive_detectorstem',
      ];
    }
    $form['edit_selected_element'] = [
      '#type' => 'submit',
      '#value' => $this->t('Edit Selected'),
      //'#value' => $this->t('Edit Selected ' . $this->single_class_name),
      '#name' => 'edit_element',
    ];
    $form['delete_selected_element'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete Selected'),
      //'#value' => $this->t('Delete Selected ' . $this->plural_class_name),
      '#name' => 'delete_element',
      '#attributes' => ['onclick' => 'if(!confirm("Really Delete?")){return false;}'],
    ];
    $form['element_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $output,
      '#js_select' => FALSE,
      '#empty' => t('No ' . $this->plural_class_name . ' found'),
    ];
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
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#name' => 'back',
    ];
    $form['space'] = [
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
          if ($this->element_type == 'platform') {
            $api->elementDel('platform',$uri);
          }
          if ($this->element_type == 'stream') {
            $api->elementDel('stream',$uri);
          }
          if ($this->element_type == 'deployment') {
            $api->elementDel('deployment',$uri);
          }
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
  
}