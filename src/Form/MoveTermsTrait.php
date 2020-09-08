<?php

namespace Drupal\instagram_catalogue\Form;

/**
 *
 */
trait MoveTermsTrait {

  /**
   * Add functions to change organize terms.
   */
  protected function addTermMixer($form) {
    $posts = $this->entityManager->getStorage('node')->loadByProperties([
      'type' => 'instagram_catalogue_post',
    ]);

    foreach ($posts as $post) {
      /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $terms */
      $terms = $post->get('field_hashtags');
      $value = $terms->getValue();
      if (!$value) {
        $post->delete();
      }
    }

    $all_term_ids = $this->termStorage->getQuery()->condition('vid', 'misc')->sort('vid', 'ASC')->sort('name', 'ASC')->execute();
    $terms = $this->termStorage->loadMultiple($all_term_ids);
    $form['terms'] = [
      '#markup' => '<hr> Terms: ' . count($all_term_ids),
      '#prefix' => '<br><br><table>',
      '#suffix' => '</table>',
    ];

    /** @var \Drupal\taxonomy\Entity\Term $term */
    foreach ($terms as $term) {
      $form['terms'][$term->id()] = [
        '#markup' => '<td>' . $term->getName() . '</td><td>' . $term->getVocabularyId() . '</td>',
        '#prefix' => '<tr>',
        '#suffix' => '</tr>',
      ];
    }

    $form['search_and_replace'] = [
      '#type' => 'textfield',
    ];

    $form['vocab']['move_vid'] = [
      '#type' => 'select',
      '#title' => 'Move TO: ',
      '#options' => $this->vocStorage->getQuery()->execute(),
    ];
    return $form;
  }

  /**
   * Combine like terms.
   */
  public function combineLikeTerms() {
    $all_names = $term_ids = $post_hashtag_ids = [];

    // Load all terms.
    $all_term_ids = $this->termStorage->getQuery()->sort('name', 'ASC')->execute();
    /** @var \Drupal\taxonomy\Entity\Term[] $terms */
    $terms = $this->termStorage->loadMultiple($all_term_ids);

    // Get all names with multiple terms.
    foreach ($terms as $term) {
      $all_names[$term->getName()][] = $term;
    }
    foreach ($all_names as $name => $terms) {
      if (count($terms) === 1) {
        unset($all_names[$name]);
      }
    }

    // Go through each name that has doubles.
    foreach ($all_names as $name => $terms) {

      // Get ids and keep last.
      foreach ($terms as $term) {
        $term_ids[] = $term->id();
      }
      $keep_term = array_pop($term_ids);

      // For each term that we want to delete.
      foreach ($term_ids as $term_id) {

        // Find all posts that reference the fated term.
        /** @var \Drupal\node\Entity\Node[] $posts */
        $posts = $this->entityManager->getStorage('node')->loadByProperties([
          'type' => 'instagram_catalogue_post',
          'field_hashtags' => $term_id,
        ]);

        if (!$posts) {
          $soon_dead = $this->termStorage->load($term_id);
          if ($soon_dead) {
            $soon_dead->delete();
          }
        }

        // Remove term from each post, and add keep term.
        foreach ($posts as $post) {
          $temp_post_hashtag_ids = $post->get('field_hashtags')->getValue();

          // Get list of hashtag ids.
          // @todo: Use array_map().
          foreach ($temp_post_hashtag_ids as $post_hashtag_id) {
            $post_hashtag_ids[] = $post_hashtag_id['target_id'];
          }

          foreach ($post_hashtag_ids as $post_key => $post_tid) {

            // Remove the old terms.
            if ($term_id === $post_tid) {
              unset($post_hashtag_ids[$post_key]);
              $soon_dead = $this->termStorage->load($term_id);
              if ($soon_dead) {
                $soon_dead->delete();
              }
            }
          }
          // Add the keep.
          if (!in_array($keep_term, $post_hashtag_ids)) {
            array_push($post_hashtag_ids, $keep_term);
            $post->set('field_hashtags', $post_hashtag_ids);
            $post->save();
          }
        }
      }
    }

  }

  // Move term to other Vocabulary.
  //    $search = $form_state->getValue('search_and_replace');
  //    $new_vid = $form_state->getValue('move_vid');.

  /**
 * @var \Drupal\taxonomy\Entity\Term[] $terms */
  // $all_term_ids = $this->termStorage->getQuery()->sort('vid', 'ASC')->sort('name', 'ASC')->execute();
  //    $terms = $this->termStorage->loadMultiple($all_term_ids);
  // If term includes the search string, move to new vocabulary.
  //    foreach ($terms as $term) {
  //      $name = $term->getName();
  //      if (strpos($name, $search) !== FALSE) {
  //        $term->set('vid', $new_vid);
  //        $term->save();
  //      }
  //    }
}
