<?php

namespace Drupal\dpl\Form;

use Drupal\rep\Vocabulary\REPGUI;

class ListDeploymentStatePage {

  public static function exec($state, $useremail, $page, $pagesize) {
    if ($state == NULL || $page == NULL || $pagesize == NULL) {
        $resp = array();
        return $resp;
    }
    $offset = -1;
    if ($page <= 1) {
      $offset = 0;
    } else {
      $offset = ($page - 1) * $pagesize;
    }
    $api = \Drupal::service('rep.api_connector');
    $elements = $api->parseObjectResponse(
      $api->deploymentByStateEmail($state, $useremail, $pagesize, $offset),
      'deploymentByStateEmail'
    );
    return $elements;
  }

  public static function total($state, $useremail) {
    if ($state == NULL) {
      return -1;
    }
    $api = \Drupal::service('rep.api_connector');
    $response = $api->deploymentSizeByStateEmail($state, $useremail);
    $listSize = -1;
    if ($response != NULL) {
      $obj = json_decode($response);
      if ($obj != NULL && $obj->isSuccessful) {
        $listSizeStr = $obj->body;
        $obj2 = json_decode($listSizeStr);
        $listSize = $obj2->total;
      }
    }
    return $listSize;

  }

  public static function link($state, $page, $pagesize) {
    $root_url = \Drupal::request()->getBaseUrl();
    if ($page > 0 && $pagesize > 0) {
     return $root_url . REPGUI::MANAGE_DEPLOYMENTS . 
          $state . '/' .
          strval($page) . '/' . 
          strval($pagesize);
    }
    return ''; 
  }

}

?>