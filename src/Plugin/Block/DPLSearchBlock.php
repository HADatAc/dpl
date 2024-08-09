<?php

namespace Drupal\dpl\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'DPLSearchBlock' block.
 *
 * @Block(
 *  id = "dpl_search_block",
 *  admin_label = @Translation("Search Deployment Criteria"),
 *  category = @Translation("Search Deployment Criteria")
 * )
 */
class DPLSearchBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('Drupal\dpl\Form\DPLSearchForm');

    return $form;
  }

}
