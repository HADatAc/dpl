<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\HASCO;

class AddStreamForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_stream_form';
  }

  protected $deployment;

  public function getDeployment() {
    return $this->deployment;
  }
  public function setDeployment($deployment) {
    return $this->deployment = $deployment;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $deploymenturi=NULL) {

    $form['#attached']['library'][] = 'dpl/dpl_tabs';

    // RETRIEVE DEPLOYMENT
    $api = \Drupal::service('rep.api_connector');
    $uri_decode=base64_decode($deploymenturi);
    $rawresponse = $api->getUri($uri_decode);
    $obj = json_decode($rawresponse);
    if ($obj->isSuccessful) {
      $this->setDeployment($obj->body);
    } else {
      \Drupal::messenger()->addError(t("Failed to retrieve Deployment."));
      self::backUrl();
      return;
    }

    $deploymentLabel = ' ';
    if (($this->getDeployment() != NULL) &&
        isset($this->getDeployment()->uri) &&
        isset($this->getDeployment()->label)) {
      $deploymentLabel = Utils::fieldToAutocomplete(
        $this->getDeployment()->uri,
        $this->getDeployment()->label
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
      '#options' => [
        'files' => $this->t('Files'),
        'messages' => $this->t('Messages'),
      ],
      '#default_value' => 'files',
    ];
    $form['tabs']['tab_content']['tab1']['stream_study'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Study'),
      '#autocomplete_route_name' => 'std.study_autocomplete',
      //'#required' => TRUE,
    ];
    $form['tabs']['tab_content']['tab1']['stream_schema'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SDD'),
      '#autocomplete_route_name' => 'std.semanticdatadictionary_autocomplete',
      //'#required' => TRUE,
    ];
    $form['tabs']['tab_content']['tab1']['stream_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#value' => 1,
      '#disabled' => true
    ];
    $form['tabs']['tab_content']['tab1']['stream_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
    ];

    // Add content for Tab 2.
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

    // Add content for Tab 3.
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
      '#default_value' => 'MQTT',
    ];
    $form['tabs']['tab_content']['tab3']['stream_ip'] = [
      '#type' => 'textfield',
      '#title' => $this->t('IP'),
    ];
    $form['tabs']['tab_content']['tab3']['stream_port'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Port'),
    ];
    $form['tabs']['tab_content']['tab3']['stream_header'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Header'),
    ];
    $form['tabs']['tab_content']['tab3']['stream_archive_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Archive ID'),
    ];

    $form['save_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#name' => 'save',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'save-button'],
      ],
    ];
    $form['cancel_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#name' => 'back',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'back-button'],
      ],
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
    //  if(strlen($form_state->getValue('stream_name')) < 1) {
    //    $form_state->setErrorByName('stream_name', $this->t('Please enter a valid name'));
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

    $dateTime = new \DateTime();
    $formattedNow = $dateTime->format('Y-m-d\TH:i:s') . '.' . $dateTime->format('v') . $dateTime->format('O');

    $deployment = '';
    if ($form_state->getValue('stream_deployment') != NULL && $form_state->getValue('stream_deployment') != '') {
      $deployment = Utils::uriFromAutocomplete($form_state->getValue('stream_deployment'));
    }

    $label = "Stream";

    try{
      $useremail = \Drupal::currentUser()->getEmail();
      $newStreamUri = Utils::uriGen('stream');
      $streamJson = '{"uri":"'.$newStreamUri.'",'.
        '"typeUri":"'.HASCO::STREAM.'",'.
        '"hascoTypeUri":"'.HASCO::STREAM.'",'.
        '"label":"'.$label.'",'.
        '"method":"'.$form_state->getValue('stream_method').'",'.
        '"deploymentUri":"'.$deployment.'",'.
        '"hasVersion":"'.($form_state->getValue('stream_version') ?? 1).'",'.
        '"comment":"'.$form_state->getValue('stream_description').'",'.
        '"messageProtocol":"'.$form_state->getValue('stream_protocol').'",'.
        '"messageIP":"'.$form_state->getValue('stream_ip').'",'.
        '"messagePort":"'.$form_state->getValue('stream_port').'",'.
        '"messageArchiveId":"'.$form_state->getValue('stream_archive_id').'",'.
        '"canUpdate":["'.$useremail.'"],'.
        '"designedAt":"'.$formattedNow.'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      $api = \Drupal::service('rep.api_connector');
      $api->elementAdd('stream',$streamJson);
      \Drupal::messenger()->addMessage(t("Stream has been added successfully."));
      self::backUrl();
      return;
    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding stream: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'dpl.add_stream');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

}
