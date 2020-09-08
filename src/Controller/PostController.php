<?php

namespace Drupal\instagram_catalogue\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Holds processors for API response.
 */
class PostController extends ControllerBase {

  const DESTINATION_DIR = 'public://instagram_catalogue/';

  /**
   * File system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  private $fileSystem;

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

    $dir = self::DESTINATION_DIR;
    $this->fileSystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY);
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
   * Create new post.
   */
  public function createPost(string $caption = '', int $comment_count = 0, string $date_posted = '', array $hashtag_term_ids = [], array $images_data = [], int $like_count = 0, string $link_to_original = '', string $title = '') {

    if (empty($title)) {
      $time = new DrupalDateTime();
      $title = $time->format('M, j Y g:i:sa');
    }

    $post = Node::create(
      [
        'field_caption' => $caption,
        'field_comment_count' => $comment_count,
        'field_date_posted' => $date_posted,
        'field_hashtags' => $hashtag_term_ids,
        'field_images' => $images_data,
        'field_like_count' => $like_count,
        'field_link_to_original_post' => $link_to_original,
        'title' => $title,
        'type' => 'instagram_catalogue_post',
      ]
    );
    $post->save();
    return $post;
  }

  /**
   * Get post by date posted.
   */
  public function getPostByDatePosted(string $date) {
    $posts = $this->entityManager->getStorage('node')
      ->loadByProperties(['type' => 'instagram_catalogue_post', 'field_date_posted' => $date]);
    return current($posts);
  }

  /**
   * Get number of existing posts.
   */
  public function getNumOfPosts(): int {
    return count($this->getAllPosts());
  }

  /**
   * Get all posts.
   */
  public function getAllPosts(): array {
    return $this->entityManager->getStorage('node')
      ->loadByProperties(['type' => 'instagram_catalogue_post']);
  }

  /**
   * If post exists in the database update a few fields.
   */
  public function updatePost(Node $post, array $new_data, string $short_caption, array $hashtag_term_ids) {
    /** @var \Drupal\file\Plugin\Field\FieldType\FileFieldItemList $images */
    $images = $post->get('field_images');
    foreach ($images as $image) {
      $image->set('title', $short_caption);
      $image->set('alt', $short_caption);
    }

    $post->set('field_caption', $new_data['caption']['text']);
    $post->set('field_hashtags', $hashtag_term_ids);
    $post->set('field_like_count', $new_data['likes']['count']);
    $post->set('field_comment_count', $new_data['comments']['count']);
    $post->save();
  }

  /**
   * Delete all posts and data.
   */
  public function deleteAllPosts() {
    $posts = $this->getAllPosts();
    foreach ($posts as $post) {
      $post->delete();
    }

    $this->messenger()->addMessage($this->t(
      '@count posts have been deleted.',
      ['@count' => count($posts)]
    ));
  }

}
