<?php

namespace Drupal\unicorn_promotional_banner\Controller;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\unicorn_core\Response\ResponseBuilder;
use Drupal\unicorn_core\Support\Http\Request;
use Drupal\unicorn_core\Support\Http\RequestParams;
use Drupal\unicorn_core\Support\Validation\ExtendedValidator;
use Drupal\unicorn_promotional_banner\Support\File\FileOperations;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Url;

class PromotionalBanner extends ControllerBase {
  private readonly Config $config;

  public function __construct(
    ConfigFactoryInterface $configFactory,
    private readonly ExtendedValidator $validator,
    private readonly FileSystemInterface $fileSystem,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly FileOperations $fileOperations,
  ) {
    $this->configFactory = $configFactory;
    $this->config = $this->configFactory->getEditable('unicorn_promotional_banner.settings');
  }

  public function update(Request $request): JsonResponse {

    $input = $request->input();
    $this->validator->validateAndThrow($input, new Collection(
      fields: [
        'status' => new Optional(new Choice(choices: [TRUE, FALSE, 1, 0, '1', '0', 'true', 'false'])),
        'width' => new Optional([new Type('numeric')]),
        'height' => new Optional([new Type('numeric')]),
        'target_url' => new Optional([
          new Url(),
        ]),
      ],
      allowExtraFields: true,
    ));

    // only do anything file related if a file was actually sent
    if($request->files->count() > 0) {
      $uploadedFile = $request->files->get('file');

      if($uploadedFile) {
        try {
          $image = $this->fileOperations->fileUpload($uploadedFile);
        }
        catch (Exception $ex) {
          return ResponseBuilder::make(['error_message' => $ex->getMessage()], Response::HTTP_BAD_REQUEST)->json();
        }
      }
    }

    $params = RequestParams::createFromRequest($request);
    $targetUrl = $request->input('target_url');
    $status = $params->getBool('status');

    if (isset($status)) {
      $this->config->set('status', $status);
    }

    if (isset($targetUrl)) {
      $this->config->set('target_url', $targetUrl);
    }

    $currentBannerPath = $this->config->get('filepath');

    if(isset($image['filepath'])) {
      $this->config->set('filepath', $image['filepath']);

      $this->config->set('width', $image['dimensions']['width']);
      $this->config->set('height', $image['dimensions']['height']);

      if($currentBannerPath && $currentBannerPath !== $image['filepath']) {
        $this->fileSystem->delete($currentBannerPath);
      }
    }

    $this->config->save();

    return ResponseBuilder::make()->json();
  }

  public function deleteImage(): JsonResponse {
    $currentBannerPath = $this->config->get('filepath');

    if($currentBannerPath) {
      $this->fileSystem->delete($currentBannerPath);
      $this->config->set('filepath', '');
      $this->config->set('width', '');
      $this->config->set('height', '');
      $this->config->save();
    }

    return ResponseBuilder::make()->json();
  }

  public function index(): JsonResponse {

    $filePath = $this->config->get('filepath');
    if($filePath) {
      $imageUrl = $this->fileUrlGenerator->generateAbsoluteString($filePath);
    }

    $response = [
      'image_url' => $filePath ? $imageUrl : null,
      'status' => $this->config->get('status'),
      'target_url' => $this->config->get('target_url'),
      'width' => $this->config->get('width'),
      'height' => $this->config->get('height')
    ];

    return ResponseBuilder::make($response)->json();
  }

}
