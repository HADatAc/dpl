<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\HASCO;

class EditStreamForm extends FormBase {

  protected $stream;

  public function getStream() {
    return $this->stream;
  }

  public function setStream($stream) {
    return $this->stream = $stream; 
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_stream_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $streamuri = NULL) {

    $form['#attached']['library'][] = 'dpl/dpl_tabs';

    // RETRIEVE STREAM
    $api = \Drupal::service('rep.api_connector');
    $uri_decode=base64_decode($streamuri);
    $rawresponse = $api->getUri($uri_decode);
    $obj = json_decode($rawresponse);
    if ($obj->isSuccessful) {
      $this->setStream($obj->body);
    } else {
      \Drupal::messenger()->addError(t("Failed to retrieve Stream."));
      self::backUrl();
      return;
    }

    $deploymentLabel = ' ';
    if (($this->getStream()->deployment != NULL) && 
        isset($this->getStream()->deployment->uri) &&
        isset($this->getStream()->deployment->label)) {
      $deploymentLabel = Utils::fieldToAutocomplete(
        $this->getStream()->deployment->uri,
        $this->getStream()->deployment->label
      );
    }

    // Add tabs to the form.
    $form['tabs'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tabs']],
    ];

    // Add the tab links.
    $form['tabs']['tab_links'] = [
      '#type' => 'markup',
      '#markup' => '<ul class="nav nav-tabs">
        <li class="nav-item"><a class="nav-link active" href="#edit-tab1">Basic Properties</a></li>
        <li class="nav-item"><a class="nav-link" href="#edit-tab2">File-Method Properties</a></li>
        <li class="nav-item"><a class="nav-link" href="#edit-tab3">Message-Method Properties</a></li>
      </ul>',
    ];

    // Tab content container.
    $form['tabs']['tab_content'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tab-content']],
    ];

    // Add content for Tab 1.
    $form['tabs']['tab_content']['tab1'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tab-pane', 'active']],
    ];

    $form['tabs']['tab_content']['tab1']['stream_deployment'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Deployment'),
      '#default_value' => $deploymentLabel,
      '#required' => TRUE,
      '#disabled' => TRUE,
    ];
    $form['tabs']['tab_content']['tab1']['stream_method'] = [
      '#type' => 'select',
      '#title' => $this->t('Method'),
      '#required' => TRUE,
      '#default_value' => $this->getStream()->method,
      '#options' => [
        'files' => $this->t('Files'),
        'messages' => $this->t('Messages'),
      ],
    ];
    $form['tabs']['tab_content']['tab1']['stream_study'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Study'),
      //'#required' => TRUE,
    ];
    $form['tabs']['tab_content']['tab1']['stream_schema'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SDD'),
      //'#required' => TRUE,
    ];
    $form['tabs']['tab_content']['tab1']['stream_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $this->getStream()->hasVersion,
    ];
    $form['tabs']['tab_content']['tab1']['stream_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getStream()->comment,
    ];

    // Tab2 Content

    $form['tabs']['tab_content']['tab2'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tab-pane']],
    ];

    $form['tabs']['tab_content']['tab2']['stream_datafile_pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Datafile Pattern'),
    ];
    $form['tabs']['tab_content']['tab2']['stream_cell_scope_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cell Scope URI'),
    ];
    $form['tabs']['tab_content']['tab2']['stream_cell_scope_name'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Cell Scope Name'),
    ];

    // Tab3 Content
    
    $form['tabs']['tab_content']['tab3'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tab-pane']],
    ];

    $form['tabs']['tab_content']['tab3']['stream_protocol'] = [
      '#type' => 'select',
      '#title' => $this->t('Protocol'),
      '#required' => TRUE,
      '#options' => [
        'MQTT' => $this->t('MQTT'),
        'HTML' => $this->t('HTML'),
        'ROS' => $this->t('ROS'),
      ],
      '#default_value' => $this->getStream()->messageProtocol,
    ];
    $form['tabs']['tab_content']['tab3']['stream_ip'] = [
      '#type' => 'textfield',
      '#title' => $this->t('IP'),
      '#default_value' => $this->getStream()->messageIP,
    ];
    $form['tabs']['tab_content']['tab3']['stream_port'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Port'),
      '#default_value' => $this->getStream()->messagePort,
    ];
    $form['tabs']['tab_content']['tab3']['stream_header'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Header'),
    ];
    $form['tabs']['tab_content']['tab3']['stream_archive_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Archive ID'),
      '#default_value' => $this->getStream()->messageArchiveId,
    ];

    $form['save_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#name' => 'save',
    ];
    $form['cancel_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#name' => 'back',
    ];
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

    //if ($button_name != 'back') {
    //  if(strlen($form_state->getValue('stream_version')) < 1) {
    //    $form_state->setErrorByName('stream_version', $this->t('Please enter a valid version'));
    //  }
    //}
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

    $label = "Stream";
      
    try{
      $uid = \Drupal::currentUser()->id();
      $useremail = \Drupal::currentUser()->getEmail();

      $streamJson = '{"uri":"'.$this->getStream()->uri.'",'.
      '"typeUri":"'.HASCO::STREAM.'",'.
      '"hascoTypeUri":"'.HASCO::STREAM.'",'.
      '"label":"'.$label.'",'.
      '"method":"'.$form_state->getValue('stream_method').'",'.
      '"deploymentUri":"'.$this->getStream()->deploymentUri.'",'.
      '"hasVersion":"'.$form_state->getValue('stream_version').'",'.
      '"comment":"'.$form_state->getValue('stream_description').'",'.
      '"messageProtocol":"'.$form_state->getValue('stream_protocol').'",'.
      '"messageIP":"'.$form_state->getValue('stream_ip').'",'.
      '"messagePort":"'.$form_state->getValue('stream_port').'",'.
      '"messageArchiveId":"'.$form_state->getValue('stream_archive_id').'",'.
      '"canUpdate":["'.$useremail.'"],'.
      '"designedAt":"'.$this->getStream()->designedAt.'",'.
      '"hasSIRManagerEmail":"'.$useremail.'"}';

      // UPDATE BY DELETING AND CREATING
      $api = \Drupal::service('rep.api_connector');
      $api->elementDel('stream',$this->getStream()->uri);
      $newStream = $api->elementAdd('stream',$streamJson);
    
      \Drupal::messenger()->addMessage(t("Stream has been updated successfully."));
      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating the Stream: ".$e->getMessage()));
      self::backUrl();
      return;
    }

  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'dpl.edit_stream');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }
  

}