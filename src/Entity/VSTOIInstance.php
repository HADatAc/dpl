<?php

namespace Drupal\dpl\Entity;

use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\REPGUI;

class VSTOIInstance {

  public static function generateHeader($elementType) {

    return $header = [
      'element_uri' => t('URI'),
      'element_type' => t('Type'),
      'element_serial' => t('Serial Number'),
    ];

  }

  public static function generateOutput($elementType, $list) {

    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();

    if ($list == NULL) {
      return array();
    }

    $output = array();
    foreach ($list as $element) {
      if ($element != null) {
        $uri = ' ';
        if (isset($element->uri) && 
            $element->uri != NULL) {
          $uri = $element->uri;
        }
        $uri = Utils::namespaceUri($uri);
        $typeLabel = ' ';
        if (isset($element->type) &&
            $element->type != NULL &&
            $element->type->label != NULL) {
          $typeLabel = $element->type->label;
        }
        $serial = ' ';
        if (isset($element->hasSerialNumber) &&
            $element->hasSerialNumber != NULL) {
          $serial = $element->hasSerialNumber;
        }
        $output[$element->uri] = [
          'element_uri' => t('<a href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($uri).'">'.$uri.'</a>'),     
          'element_type' => $typeLabel,
          'element_serial' => $serial,
        ];
      }
    }
    return $output;

  }

}