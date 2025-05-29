<?php
namespace Drupal\dpl\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;

class MqttMessageAjaxController extends ControllerBase {

  public function recordMessageAjax(Request $request) {

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $archive_id = $request->query->get('archive_id');
    $ip = $request->query->get('ip');
    $port = $request->query->get('port');
    $topic = $request->query->get('topic');

    // Ler nova mensagem (adaptar o comando SSH como no teu Form)
    $ssh_cmd = "ssh -i /var/www/.ssh/graxiom_main.pem -o StrictHostKeyChecking=no ubuntu@$ip 'tmux capture-pane -pt mqtt -S -1 -e'";
    $output = shell_exec($ssh_cmd);

    if (empty(trim($output))) {
      return new JsonResponse(['status' => 'no-message']);
    }

    preg_match_all('/\{.*?\}/s', $output, $matches);
    $messages = $matches[0] ?? [];
    $last_msg = end($messages);

    if (isset($_SESSION['last_mqtt_message']) && $_SESSION['last_mqtt_message'] === $last_msg) {
        return new JsonResponse(['status' => 'duplicate']);
    }
    
    $_SESSION['last_mqtt_message'] = $last_msg;

    // Gravar no Excel (append)
    $directory = 'private://streams/messageFiles/';
    \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $filepath = \Drupal::service('file_system')->realpath($directory . "Messages{$archive_id}_0.xlsx");

    if (file_exists($filepath)) {
      $spreadsheet = IOFactory::load($filepath);
    } else {
      $spreadsheet = new Spreadsheet();
      $spreadsheet->getActiveSheet()->fromArray(['Timestamp', 'Raw JSON'], NULL, 'A1');
    }

    $sheet = $spreadsheet->getActiveSheet();
    $row = $sheet->getHighestRow() + 1;
    $sheet->setCellValue("A$row", date('Y-m-d H:i:s'));
    $sheet->setCellValue("B$row", $last_msg);

    $writer = new Xlsx($spreadsheet);
    $writer->save($filepath);

    return new JsonResponse(['status' => 'ok', 'row' => $row]);
  }
}
