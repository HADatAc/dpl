<?php

namespace Drupal\dpl\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\rep\Vocabulary\VSTOI;

class APIPlatformsController extends ControllerBase {

  /**
   * Endpoint to list platforms.
   */
  public function listPlatforms() {
    // Obtém o email do usuário atual.
    $manager_email = \Drupal::currentUser()->getEmail();
    // Outras variáveis podem ser utilizadas se necessário.
    $pagesize = 99999;
    $offset = 0;

    // Recupera o serviço rep.api_connector.
    $api = \Drupal::service('rep.api_connector');
    $platforms = $api->parseObjectResponse(
      $api->listByManagerEmail('platforminstance', $manager_email, $pagesize, $offset),
      'listByManagerEmail'
    );

    return new JsonResponse($platforms);
  }

  /**
   * Endpoint to edit a platform via POST.
   * Expects a JSON payload containing 'uri' and fields to update.
   */
  public function editPlatform(Request $request) {
    // Decodifica o corpo da requisição JSON.
    $content = $request->getContent();
    $requestData = json_decode($content, TRUE);

    if (!$requestData) {
      return new JsonResponse([
        'status'  => 'error',
        'message' => 'Invalid JSON data.',
      ]);
    }

    // Verifica se a URI foi informada.
    $uriFromRequest = $requestData['uri'] ?? NULL;
    if (!$uriFromRequest) {
      return new JsonResponse([
        'status'  => 'error',
        'message' => 'URI is required.',
      ]);
    }

    // Recupera o serviço rep.api_connector.
    $api = \Drupal::service('rep.api_connector');

    try {
      // Recupera dados adicionais da plataforma através do serviço.
      $rawResponse = $api->getUri($uriFromRequest);
      $obj = json_decode($rawResponse, TRUE);
      $data = $obj['body'] ?? [];

      // Usa os dados recuperados ou fallback para o valor enviado.
      $uri = $data['uri'] ?? $uriFromRequest;
      $new_name = $data['name'] ?? '';

      // Constrói o payload JSON para atualizar a plataforma.
      $platformJson = json_encode([
        'uri'                => $uri,
        'superUri'           => VSTOI::PLATFORM,
        'hascoTypeUri'       => VSTOI::PLATFORM,
        'label'              => $requestData['platform_name'] ?? '',
        'hasVersion'         => $requestData['platform_version'] ?? '',
        'comment'            => $requestData['platform_description'] ?? '',
        'hasSIRManagerEmail' => $data['hasSIRManagerEmail'] ?? '',
      ]);

      // Atualiza a plataforma: primeiro deleta e depois adiciona novamente.
      $deleteResponse = $api->elementDel('platform', $uri);
      if (!$deleteResponse->isSuccessful()) {
        $deleteMsg = json_decode($deleteResponse->getContents())->message ?? 'Unknown error during deletion';
        return new JsonResponse([
          'status'     => 'error',
          'message'    => 'Failed to update Platform: ' . $deleteMsg,
          'uri_edited' => $uri,
          'new_name'   => $new_name,
        ]);
      }

      $addResponse = $api->elementAdd('platform', $platformJson);
      $msg = json_decode($addResponse->getContents());

      if ($msg && !empty($msg->isSuccessful)) {
        return new JsonResponse([
          'status'     => 'success',
          'message'    => 'Platform edited successfully!',
          'uri_edited' => $uri,
          'new_name'   => $new_name,
        ]);
      }
      else {
        $errorMsg = $msg->message ?? 'Unknown error during addition';
        return new JsonResponse([
          'status'     => 'error',
          'message'    => 'Failed to update Platform: ' . $errorMsg,
          'uri_edited' => $uri,
          'new_name'   => $new_name,
        ]);
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('dpl')->error($e->getMessage());
      return new JsonResponse([
        'status'     => 'error',
        'message'    => $e->getMessage(),
        'uri_edited' => $uriFromRequest,
      ]);
    }
  }

}
