<?php

namespace Drupal\dpl\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class AccordionForm extends FormBase {

  public function getFormId() {
    return 'accordion_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    // Attach custom library.
    $form['#attached']['library'][] = 'dpl/dpl_accordion';

    // Initialize accordion container.
    $form['accordion'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['accordion']],
    ];

    // Define each accordion item.
    for ($i = 1; $i <= 3; $i++) {
      $form['accordion']['item_' . $i] = $this->buildAccordionItem($i, $form_state);
    }

    // Add a submit button.
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  private function buildAccordionItem($id, FormStateInterface $form_state) {
    $active_class = ($id == 1) ? 'show' : '';
    //$active_class = 'show';

    // Build the sub-form for each accordion item.
    $sub_form = [
      '#type' => 'container',
      '#attributes' => ['class' => ['accordion-body']],
      'field_' . $id . '_1' => [
        '#type' => 'textfield',
        '#title' => $this->t('Field 1 for Option @id', ['@id' => $id]),
      ],
      'field_' . $id . '_2' => [
        '#type' => 'textfield',
        '#title' => $this->t('Field 2 for Option @id', ['@id' => $id]),
      ],
    ];

    // Render the sub-form elements.
    $sub_form_rendered = \Drupal::service('renderer')->render($sub_form);

    // Build the accordion item as a Drupal render array.
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['card']],
      'header' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['card-header']],
        'button' => [
          '#type' => 'button',
          '#value' => $this->t('<h5 class="mb-0">Option ' . $id . '</h5>'),
          '#attributes' => [
            'class' => ['btn', 'btn-link', 'transparent-button'],
            'type' => 'button',
            'data-bs-toggle' => 'collapse',
            'data-bs-target' => '#collapse' . $id,
            'aria-expanded' => 'true',
            'aria-controls' => 'collapse' . $id,
          ],
        ],
      ],
      'collapse' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'collapse' . $id,
          'class' => ['collapse', $active_class],
          'aria-labelledby' => 'heading' . $id,
          'data-bs-parent' => '#accordionExample',
        ],
        'body' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['card-body']],
          '#markup' => $sub_form_rendered,
        ],
      ],
    ];
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Handle form submission.
    $this->messenger()->addMessage($this->t('Form has been submitted.'));
  }
}
