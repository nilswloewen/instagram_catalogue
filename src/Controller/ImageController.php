<?php

namespace Drupal\instagram_catalogue\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Holds processors for API response.
 */
class ImageController extends ControllerBase {

  /**
   * File system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  private $fileSystem;

  /**
   * File storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $fileStorage;

  /**
   * SMPostsSettingsForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Service: 'entity_type.manager'.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   Service: 'file_system'.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system) {
    $this->entityManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->fileStorage = $this->entityManager->getStorage('file');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('file_system')
    );
  }

  /**
   * Save images and return image data.
   */
  public function saveImages(array $new_data, string $img_title): array {
    if ($new_data['type'] === 'image') {
      $images_data = $this->saveImageAndGetData($new_data['images']['standard_resolution'], $img_title);
    }
    elseif ($new_data['type'] === 'carousel') {
      foreach ($new_data['carousel_media'] as $media) {
        if ($media['type'] === 'video') {
          continue;
        }
        $images_data[] = $this->saveImageAndGetData($media['images']['standard_resolution'], $img_title);
      }
    }

    return $images_data ?? [];
  }

  /**
   * Save image locally for efficient caching.
   */
  protected function saveImageAndGetData(array $image_data, string $short_caption): array {
    $image_file = system_retrieve_file($image_data['url'], PostController::DESTINATION_DIR, TRUE, FileSystemInterface::EXISTS_REPLACE);

    return [
      'alt' => $short_caption,
      'height' => $image_data['height'],
      'target_id' => $image_file->id(),
      'title' => $short_caption,
      'width' => $image_data['width'],
    ];
  }

  /**
   * Get all image files.
   */
  protected function getAllImageFiles(): array {
    $file_ids = $this->fileStorage->getQuery()->condition('uri', 'instagram_catalogue', 'CONTAINS')->execute();
    return $this->fileStorage->loadMultiple($file_ids);
  }

  /**
   * Delete all image files.
   *
   * @todo: Delete all thumbnails created by image styles.
   */
  public function deleteImageFiles() {
    $image_files = $this->getAllImageFiles();

    if ($image_files) {
      foreach ($image_files as $image_file) {
        $image_file->delete();
      }

      $this->messenger()->addMessage($this->t(
        '@count images have been deleted.',
        ['@count' => count($image_files)]
      ));
    }

    // Delete all un-managed files created from unzipping personal data upload.
    $this->fileSystem->deleteRecursive(PostController::DESTINATION_DIR);
  }

}
