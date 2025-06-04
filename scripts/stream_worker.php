<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\DrupalKernel;

$autoloader = require_once '/opt/drupal/web/autoload.php';

$kernel = DrupalKernel::createFromRequest(
  Request::createFromGlobals(),
  $autoloader,
  'dev'
);
$kernel->boot();
$request = Request::createFromGlobals();
$kernel->preHandle($request);
\Drupal::setContainer($kernel->getContainer());

$stream_id = $argv[1];
$archive_id = $argv[2];
$ip = $argv[3];
$port = $argv[4];
$topic = $argv[5];

$fs = \Drupal::service('file_system');
$directory = 'private://streams/messageFiles/';
$fs->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);

$filepath = $fs->realpath($directory . "Messages{$archive_id}_0.xlsx");
$last_message = '';

while (true) {
  $api = \Drupal::service('rep.api_connector');
  $stream = $api->parseObjectResponse($api->getUri($stream_id), 'getUri');

  if ($stream->hasMessageStatus !== 'http://hadatac.org/ont/hasco/Recording') {
    break;
  }

  $ssh_cmd = "ssh -i /var/www/.ssh/graxiom_main.pem -o StrictHostKeyChecking=no ubuntu@$ip 'tmux capture-pane -pt " . escapeshellarg($topic) . " -S -1 -e'";
  //\Drupal::logger('stream_record')->debug('SSH CMD: @cmd', ['@cmd' => $ssh_cmd]);
  $output = shell_exec($ssh_cmd);
  //\Drupal::logger('stream_record')->debug('Output do SSH: <pre>@output</pre>', ['@output' => print_r($output, TRUE)]);

  if (!is_string($output) || trim($output) === '') {
    sleep(20);
    continue;
  }

  preg_match_all('/\{.*?\}/s', $output, $matches);
  $messages = $matches[0] ?? [];
  $last_msg = end($messages);

  if ($last_msg === $last_message) {
    sleep(20);
    continue;
  }

  $last_message = $last_msg;

  if (file_exists($filepath)) {
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filepath);
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

  sleep(20);
}