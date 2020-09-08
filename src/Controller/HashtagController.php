<?php

namespace Drupal\instagram_catalogue\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Control terms.
 */
class HashtagController extends ControllerBase {

  /**
   * Term storage.
   *
   * @var \Drupal\taxonomy\TermStorage
   */
  private $termStorage;

  /**
   * HashtagHandler constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Service: 'entity_type.manager'.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->termStorage = $entity_type_manager->getStorage('taxonomy_term');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Parse out hashtags from caption.
   */
  public static function parseOutHashtagsFromCaption(string $caption): array {
    $hashtags = [];

    // Split by whitespace.
    $words = preg_split('#\s+#', $caption);
    foreach ($words as $index => $word) {

      // If #hashtag#hashtag2, split by hashtag.
      preg_match_all("/(#\w+)/", $word, $tags);
      foreach ($tags[0] as $tag) {
        if (!in_array($tag, $hashtags)) {
          $hashtags[$tag] = $tag;
        }

        // Remove hashtag from caption.
        unset($words[$index]);
      }
    }
    $caption_without_hashtags = implode(' ', $words);

    return [$caption_without_hashtags, $hashtags];
  }

  /**
   * Create taxonomy terms.
   */
  public function createHashtagTerms(array $new_hashtags): array {

    $new_hashtags = $this->removeTattooHashtags($new_hashtags);

    // Alphabetize list.
    ksort($new_hashtags);

    // Compare each new hashtag to ones that exist.
    foreach ($new_hashtags as $new_hashtag) {
      $term = current($this->termStorage->loadByProperties(['name' => $new_hashtag]));
      // If term does not exist, create a new one.
      if (!$term) {
        $term = Term::create([
          'vid' => 'misc',
          'name' => $new_hashtag,
          'parent' => [],
        ]);
        $term->save();
      }
      $term_ids[] = $term->id();
    }

    return $term_ids ?? [];
  }

  /**
   * Parse out hashtags that include 'tattoo'.
   */
  private function removeTattooHashtags(array $hashtags): array {
    foreach ($hashtags as $hashtag) {
      if (strpos($hashtag, 'tattoo') && !strpos($hashtag, 'tattoos')
        && $hashtag !== '#tattoo') {

        $new_tag = str_replace('tattoo', '', $hashtag);
        $new_hashtags[$new_tag] = $new_tag;
        $new_hashtags['#tattoo'] = '#tattoo';
        unset($new_hashtags[$hashtag]);
      }
    }

    return $hashtags;
  }

  /**
   * Delete all hashtag taxonomy terms.
   */
  public function deleteHashtags() {
    $hashtag_term_ids = $this->termStorage->getQuery()->execute();
    $hashtag_terms = $this->termStorage->loadMultiple($hashtag_term_ids);

    if ($hashtag_terms) {
      foreach ($hashtag_terms as $term) {
        $term->delete();
      }
      $this->messenger()->addMessage($this->t('@count hashtag terms have been deleted.',
        ['@count' => count($hashtag_terms)]));
    }
  }

}
