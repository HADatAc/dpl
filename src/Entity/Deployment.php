<?php

namespace Drupal\dpl\Entity;

use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\REPGUI;

class Deployment {

  public static function generateHeader($state) {

    if ($state == 'design') {
      return $header = [
        'element_uri' => t('URI'),
        'element_datetime' => t('Design Time'),
        'element_platform' => t('Associated Platform'),
        'element_instrument' => t('Associated Instrument'),
      ];
    } else {
      return $header = [
        'element_uri' => t('URI'),
        'element_datetime' => t('Execution Time'),
        'element_platform' => t('Associated Platform'),
        'element_instrument' => t('Associated Instrument'),
      ];
    }

  }

  public static function generateOutput($state, $list) {

    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();

    $output = array();
    foreach ($list as $element) {
      $uri = ' ';
      if ($element->uri != NULL) {
        $uri = $element->uri;
      }
      $uri = Utils::namespaceUri($uri);
      $label = ' ';
      if ($element->label != NULL) {
        $label = $element->label;
      }
      $platform = ' ';
      if (isset($element->platform) && isset($element->platform->label)) {
        $platform = $element->platform->label;
      }
      $instrument = ' ';
      if (isset($element->instrument) && isset($element->instrument->label)) {
        $instrument = $element->instrument->label;
      }
      $datetime = ' ';
      if ($state == 'design') {
        if (isset($element->designedAt)) {
          $dateTimeRaw = new \DateTime($element->designedAt);
          $datetime = $dateTimeRaw->format('F j, Y \a\t g:i A');
        }
      } else {
        if (isset($element->startedAt)) {
          $dateTimeRaw = new \DateTime($element->startedAt);
          $datetime = $dateTimeRaw->format('F j, Y \a\t g:i A');
        }
      }

      $output[$element->uri] = [
        'element_uri' => t('<a href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($uri).'">'.$uri.'</a>'),     
        'element_datetime' => $datetime,     
        'element_platform' => $platform,
        'element_instrument' => $instrument,
      ];
    }
    return $output;

  }

}