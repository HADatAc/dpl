<?php

namespace Drupal\dpl\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;
use Drupal\rep\Vocabulary\VSTOI;

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

  public function execActuator(Request $request) {
    return self::exec($request, 'actuator');
  }

  public function execPlatformInstance(Request $request) {
    return self::exec($request, 'platforminstance');
  }

  public function execInstrumentInstance(Request $request) {
    return self::exec($request, 'instrumentinstance');
  }

  public function execDetectorInstance(Request $request) {
    return self::exec($request, 'detectorinstance');
  }

  public function execActuatorInstance(Request $request) {
    return self::exec($request, 'actuatorinstance');
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
    // foreach ($elements as $element) {
    //   if (isset($element) &&
    //       isset($element->label) && ($element->label != "") &&
    //       isset($element->uri) && ($element->uri != "")) {
    //     $results[] = [
    //       'value' => $element->label . ' [' . $element->uri . ']',
    //       'label' => $element->label,
    //     ];
    //   }
    // }
    foreach ($elements as $element) {
      if ($elementtype === 'instrumentinstance') {
        // 1) Se vier status “Deprecated” ou “Deployed”, pula este elemento
        if (isset($element->hasStatus)
            && in_array($element->hasStatus, [VSTOI::DEPLOYED, VSTOI::DEPRECATED], TRUE)) {
          continue;
        }
      }

      // 2) Mantém somente os que têm label e URI válidos
      if (isset($element->label, $element->uri)
          && $element->label !== ''
          && $element->uri !== '') {
        $results[] = [
          'value' => $element->label . ' [' . $element->uri . ']',
          'label' => $element->label,
        ];
      }
    }
    return new JsonResponse($results);
  }

}
