<?php

declare(strict_types=1);

namespace Drupal\unicorn_promotional_banner\Support\File;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\File\Event\FileUploadSanitizeNameEvent;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\unicorn_core\Support\Validation\ExtendedValidator;
use Drupal\unicorn_promotional_banner\Configuration\PromotionalBannerConfig;
use RuntimeException;
use SimpleXMLElement;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class FileOperations
{
  public function __construct(
    private readonly ImageFactory $imageFactory,
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly ExtendedValidator $validator,
    private readonly FileSystemInterface $fileSystem,
    #[Autowire(service: 'transliteration')]
    private readonly TransliterationInterface $transliteration,
    private readonly PromotionalBannerConfig $promotionalBannerConfig,
  ) {}

  /**
   * @return array{filepath: string, dimensions: array{width: int|null, height: int|float|null}}
   *
   * @throws ValidationFailedException
   * @throws RuntimeException
   */
  public function fileUpload(UploadedFile $uploadedFile): array {

    $this->validator->validateAndThrow($uploadedFile, [
      new File(maxSize: (string) $this->promotionalBannerConfig->getMaxFileUpload()),
    ]);

    /** Extension can't be validated via the Symfony File constraint here: it
     * derives the file's mime type from Drupal's own guesser, which is
     * extension-based and always resolves the raw upload tmp path (no
     * extension) to application/octet-stream, so every upload would fail.
     */
    $extension = strtolower($uploadedFile->getClientOriginalExtension());
    $this->validateAllowedExtension($extension);

    $directory = $this->promotionalBannerConfig->getSteamWrapper() . $this->promotionalBannerConfig->getDirectory();
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    $filename = $this->getSecureFileName($uploadedFile->getClientOriginalName());
    $data = file_get_contents($uploadedFile->getPathname());

    if (!$data) {
      throw new RuntimeException('Could not read uploaded file');
    }

    $filepath = $this->fileSystem->saveData($data, $directory . '/' . $filename, FileExists::Replace);

    $result = match (true) {
      $extension === 'svg' => $this->handleSvgUpload($filepath, $uploadedFile),
      default => $this->processAsWebp($filepath, $extension),
    };

    return [
      'filepath' => $result['filepath'],
      'dimensions' => [
        'width' => $result['width'],
        'height' => $result['height'],
      ],
    ];
  }

  private function validateAllowedExtension(string $extension): void {
    if (!in_array($extension, $this->promotionalBannerConfig->getAllowedExtensions(), true)) {
      throw new RuntimeException('The selected format of the file is not allowed.');
    }
  }

  /**
   * Sanitizes and transliterates an uploaded filename before it hits disk.
   */
  public function getSecureFileName(string $filename): string {
    $event = new FileUploadSanitizeNameEvent($filename, $this->promotionalBannerConfig->getAllowedExtensionsString());
    $this->eventDispatcher->dispatch($event);
    $filename = $event->getFilename();

    $filename = $this->transliteration->transliterate($filename);

    return str_replace(' ', '_', trim($filename));
  }

  /**
   * SVG has no raster dimensions Drupal's image toolkits (GD/Imagick) can
   * read, so we parse the file's own width/height (or viewBox) attributes instead.
   */
  /**
   * @return array{filepath: string, width: int, height: float}
   */
  private function handleSvgUpload(string $filepath, UploadedFile $uploadedFile): array {
    $data = file_get_contents($uploadedFile->getPathname());

    if (!$data) {
      throw new RuntimeException('Could not read uploaded file');
    }

    $svgData = simplexml_load_string($data);

    if (!$svgData) {
      throw new RuntimeException('Could not parse the uploaded SVG file.');
    }

    $svgDimensions = $this->getSvgImgDimensions($svgData);

    $aspectRatio = (float) $svgDimensions['width'] / (float) $svgDimensions['height'];

    return [
      'filepath' => $filepath,
      'width' => $this->promotionalBannerConfig->getImageScaleWidth(),
      'height' => floor($this->promotionalBannerConfig->getImageScaleWidth() / $aspectRatio),
    ];
  }

  /**
   * Converts the upload to webp (unless it already is one) and scales it to
   * the configured width. If the extension changed, the original file is
   * dropped in favor of the new webp path.
   *
   * @return array{filepath: string, width: int|null, height: int|null}
   */
  private function processAsWebp(string $filepath, string $extension): array {
    $image = $this->imageFactory->get($filepath);
    $webpPath = $filepath;

    if ($extension !== 'webp') {
      $image->convert('webp');
      $webpPath = preg_replace('/\.' . preg_quote($extension, '/') . '$/', '.webp', $filepath);

      if (is_null($webpPath)) {
        throw new RuntimeException('Could not derive the webp filepath.');
      }
    }

    $image->scale($this->promotionalBannerConfig->getImageScaleWidth());
    $image->save($webpPath);

    if ($webpPath !== $filepath) {
      $this->fileSystem->delete($filepath);
    }

    return [
      'filepath' => $webpPath,
      'width' => $image->getWidth(),
      'height' => $image->getHeight(),
    ];
  }

  /**
   * @return array{width: string, height: string}
   */
  private function getSvgImgDimensions(SimpleXMLElement $svg): array {
    $imageWidth = (string) $svg->attributes()->width;
    $imageHeight = (string) $svg->attributes()->height;

    if(empty($imageHeight) || empty($imageWidth)) {
      $viewBox = $svg->attributes()->viewBox;

      if(!empty($viewBox)) {
        $viewBox = explode(' ', (string) $viewBox);
        $imageWidth = $viewBox[2] ?? null;
        $imageHeight = $viewBox[3] ?? null;
      }
    }

    if(empty($imageHeight) || empty($imageWidth)) {
      throw new RuntimeException('Can\'t extract the width and height of the SVG file.');
    }

    return [
      'width' => $imageWidth,
      'height' => $imageHeight
    ];
  }
}
