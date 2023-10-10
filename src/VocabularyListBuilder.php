<?php

namespace Drupal\group_taxonomy;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\taxonomy\VocabularyListBuilder as VocabularyListBuilderBase;

/**
 * A VocabularyListBuilder to list  only taxonomies that user has access to.
 */
class VocabularyListBuilder extends VocabularyListBuilderBase {

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entities = parent::load();
    $taxonomies = [];
    // Remove vocabularies the current user doesn't have any access for.
    foreach ($entities as $id => $entity) {
      $account = \Drupal::currentUser();
      $access = \Drupal::service('group_taxonomy.taxonomy')->taxonomyVocabularyAccess('view', $entity, $account);
      if ($access instanceof AccessResultAllowed) {
        $taxonomies[$id] = $entity;
      }
    }

    return $taxonomies;
  }

}
