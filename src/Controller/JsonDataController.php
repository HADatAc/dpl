<?php

namespace Drupal\dpl\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\rep\Entity\MetadataTemplate as DataFile;
use Drupal\Component\Utility\Html;

/**
 * AJAX controller to populate the “Stream Data Files” card with filtering
 * and pagination.
 */
class JsonDataController extends ControllerBase {

  /**
   * AJAX callback for data files + messages.
   *
   * Query parameters:
   *   - studyUri   Base64-encoded study URI
   *   - streamUri  Base64-encoded stream URI
   *   - page       (optional) page number, default 1
   *   - pagesize   (optional) items per page, default 10
   */
  public function streamDataAjax(Request $request) {
    // 1) Decode study & stream from base64.
    $studyKey   = $request->query->get('studyUri');
    $streamKey  = $request->query->get('streamUri');
    $studyUri   = base64_decode($studyKey);
    $streamUri  = base64_decode($streamKey);

    // 2) Pagination parameters.
    $page      = max(1, (int) $request->query->get('page', 1));
    $pageSize  = max(1, (int) $request->query->get('pagesize', 10));
    $offset    = ($page - 1) * $pageSize;

    // 3) Manager email.
    $managerEmail = \Drupal::currentUser()->getEmail();

    // 4) Fetch total count (already returns an array).
    $totalArr = \Drupal::service('rep.api_connector')
      ->parseObjectResponse(
        \Drupal::service('rep.api_connector')
          ->listSizeByManagerEmailByStudy($studyUri, 'da', $managerEmail),
        'listSizeByManagerEmailByStudy'
      );
    $totalDAs = !empty($totalArr['total']) ? (int) $totalArr['total'] : 0;

    // 5) Fetch raw page of DAs (already returns an array).
    $rawList = \Drupal::service('rep.api_connector')
      ->parseObjectResponse(
        \Drupal::service('rep.api_connector')
          ->listByManagerEmailByStudy($studyUri, 'da', $managerEmail, $pageSize, $offset),
        'listByManagerEmailByStudy'
      );
    if (!is_array($rawList)) {
      $rawList = [];
    }

    // 6) Filter by hasDataFile->streamUri.
    $filtered = array_filter($rawList, function ($element) use ($streamUri) {
      return isset($element->hasDataFile->streamUri)
        && $element->hasDataFile->streamUri === $streamUri;
    });
    $filtered = array_values($filtered);

    // 7) Build header & rows via DataFile.
    $header = DataFile::generateStreamHeader();
    $rows   = DataFile::generateStreamOutputCompact('da',$filtered);

    foreach ($rows as $key => &$row) {
      if (isset($row['element_log'])) {
        $html = $row['element_log'];
        $row['element_log'] = [
          'data' => [
            '#markup' => $html,
          ],
        ];
      }
      if (isset($row['element_operations'])) {
        $html = $row['element_operations'];
        $row['element_operations'] = [
          'data' => [
            '#markup' => $html,
          ],
        ];
      }
    }
    unset($row);

    // 8) Render the table.
    $tableBuild = [
      '#theme'      => 'table',
      '#header'     => $header,
      '#rows'       => $rows,
      '#attributes' => ['class' => ['table','table-sm']],
    ];
    $filesHtml = \Drupal::service('renderer')->renderRoot($tableBuild);

    $filesHtml = Html::decodeEntities($filesHtml);

    // 9) Build pager.
    $totalPages = (int) ceil($totalDAs / $pageSize);
    $pagerHtml = '<nav><ul class="pagination">';
    for ($p = 1; $p <= $totalPages; $p++) {
      $active = ($p === $page) ? ' active' : '';
      $pagerHtml .= '<li class="page-item' . $active . '">'
        . '<a href="#" class="page-link dpl-files-page" data-page="' . $p . '">'
        . $p . '</a></li>';
    }
    $pagerHtml .= '</ul></nav>';

    // 10) Placeholder for messages.
    $messagesHtml = '<p>' . $this->t('No messages for this stream.') . '</p>';

    // 11) Return JSON.
    return new JsonResponse([
      'files'      => $filesHtml,
      'filesPager' => $pagerHtml,
      'messages'   => $messagesHtml,
    ]);
  }

}
