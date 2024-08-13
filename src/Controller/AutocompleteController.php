<?php

namespace Drupal\dpl\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;

/**
 * Class AutocompleteController
 * @package Drupal\dpl\Controller
 */
class AutocompleteController extends ControllerBase{

  public function execPlatform(Request $request) {
    return self::exec($request, 'platform');
  }

  public function execInstrument(Request $request) {
    return self::exec($request, 'instrument');
  }

  public function execDetector(Request $request) {
    return self::exec($request, 'detector');
  }



  /**
   * @return JsonResponse
   */
  private function exec(Request $request, $elementtype) {
    //public function execPlatform(Request $request) {
    //$elementtype = 'platform';
    $results = [];
    if ($elementtype == NULL) {
      return new JsonResponse($results);
    }
    $input = $request->query->get('q');
    if (!$input) {
      return new JsonResponse($results);
    }
    $keyword = Xss::filter($input);
    $api = \Drupal::service('rep.api_connector');
    $element_list = $api->listByKeyword($elementtype,$keyword,10,0);
    $obj = json_decode($element_list);
    $elements = [];
    if ($obj->isSuccessful) {
      $elements = $obj->body;
    }
    foreach ($elements as $element) {
      if (isset($element) &&
          isset($element->label) && ($element->label != "") &&
          isset($element->uri) && ($element->uri != "")) {
        $results[] = [
          'value' => $element->label . ' [' . $element->uri . ']',
          'label' => $element->label,
        ];
      }
    }
    return new JsonResponse($results);
  }

}