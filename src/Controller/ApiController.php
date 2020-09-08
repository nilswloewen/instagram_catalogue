<?php

namespace Drupal\instagram_catalogue\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\File\FileSystemInterface;
use DateTimeZone;
use Drupal\Core\Url;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Holds processors for API response.
 */
class ApiController extends ControllerBase {

  /**
   * File system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  private $fileSystem;

  /**
   * Logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;


  /**
   * PostController.
   *
   * @var \Drupal\instagram_catalogue\Controller\PostController
   */
  private $postController;

  /**
   * HashtagController.
   *
   * @var \Drupal\instagram_catalogue\Controller\HashtagController
   */
  private $hashtagController;

  /**
   * Image controller.
   *
   * @var \Drupal\instagram_catalogue\Controller\ImageController
   */
  private $imageController;

  /**
   * SMPostsSettingsForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Service: 'entity_type.manager'.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   Service: 'file_system'.
   * @param \Psr\Log\LoggerInterface $logger
   *   Service: 'logger.factor'.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system, LoggerInterface $logger) {
    $this->entityManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->logger = $logger;

    $this->postController = new PostController($entity_type_manager, $file_system);
    $this->hashtagController = new HashtagController($entity_type_manager);
    $this->imageController = new ImageController($entity_type_manager, $file_system);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('logger.factory')->get('action')
    );
  }

  /**
   * Get access token.
   */
  public function getAccessToken() : string {
    $config = self::config('instagram_catalogue.settings');
    return $config->get('access_token') ?? '';
  }

  /**
   * Run updates.
   */
  public function queryForNewPosts() {
    $num_posts_before_update = $this->postController->getNumOfPosts();

    $access_token = $this->getAccessToken();
    $apiResponse = $this->queryApi($access_token);
    $this->processApiResponse($apiResponse);

    $num_posts_after_update = $this->postController->getNumOfPosts();
    $count = $num_posts_before_update - $num_posts_before_update;

    // Report success!
    $this->messenger()->addMessage($this->t(
      '@count Instagram Posts are now saved/updated from Instagram API. @total posts total.',
      ['@count' => $count, '@total' => $num_posts_after_update]
    ));
  }

  /**
   * Queries Instagram API.
   */
  private function queryApi(string $access_token) : array {
    $client = new Client();
    $api_url = Url::fromUri('https://api.instagram.com/v1/users/self/media/recent/', [
      'query' => ['access_token' => $access_token],
    ]);

    $this->logger->notice(Link::fromTextAndUrl(
      'Instagram Catalogue queried: ' . $api_url->toString(),
      $api_url
    )->toString());

    try {
      $api_response = $client->get($api_url->toString(), ['headers' => ['Accept' => 'application/json']]);
      $response_body = $api_response->getBody();
    }
    catch (ClientException $e) {
      $this->messenger()->addError($e->getMessage());
    }

    return Json::decode($response_body ?? '') ?? [];
  }

  /**
   * Process and save data retrieved from Instagram API.
   */
  public function processApiResponse(array $api_response) {
    foreach ($api_response['data'] as $new_data) {
      $caption = $new_data['caption']['text'];
      if (empty($caption)) {
        continue;
      }

      $created_date = $this->convertTimestampToDate($new_data['created_time']);
      $date_posted = $created_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

      list($caption, $hashtags) = $this->hashtagController->parseOutHashtagsFromCaption($caption);
      if (empty($caption)) {
        $caption = $created_date->format('M, j Y g:i:sa');
      }

      $short_caption = substr($caption, 0, 255);

      $hashtag_term_ids = $this->hashtagController->createHashtagTerms($hashtags);
      $post = $this->postController->getPostByDatePosted($date_posted);
      if ($post) {
        $this->postController->updatePost($post, $new_data, $short_caption, $hashtag_term_ids);
        continue;
      }

      $this->postController->createPost(
        $caption,
        $new_data['comments']['count'],
        $date_posted,
        $hashtag_term_ids,
        $this->imageController->saveImages($new_data, $short_caption),
        $new_data['likes']['count'],
        $new_data['link'],
        $short_caption
      );
    }
  }

  /**
   * Convert Unix timestamp to time in hopefully the right timezone.
   */
  public function convertTimestampToDate(int $timestamp) : DrupalDateTime {
    $date_posted = date(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $timestamp);
    $date_posted = new DrupalDateTime($date_posted);
    $date_posted->setTimezone(new DateTimeZone('PST'));
    return $date_posted;
  }

  /**
   * Deletes all data saved from Instagram.
   */
  public function deleteAllPostData() {
    $this->postController->deleteAllPosts();
    $this->imageController->deleteImageFiles();
    $this->hashtagController->deleteHashtags();
  }

}
