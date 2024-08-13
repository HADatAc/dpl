<?php

namespace Drupal\dpl\Entity;

use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\REPGUI;

class Stream {

  public static function generateHeader() {

    return $header = [
      'element_uri' => t('URI'),
      'element_name' => t('Name'),
      'element_version' => t('Version'),
    ];

  }

  public static function generateOutput($list) {

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
      $version = ' ';
      if ($element->hasVersion != NULL) {
        $version = $element->hasVersion;
      }
      $output[$element->uri] = [
        'element_uri' => t('<a href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($uri).'">'.$uri.'</a>'),     
        'element_name' => $label,     
        'element_version' => $version,
      ];
    }
    return $output;

  }

}