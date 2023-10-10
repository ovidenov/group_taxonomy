<?php

namespace Drupal\group_taxonomy\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\Core\Routing\RouteMatch;

/**
 * Checks access for displaying taxonomy and term pages.
 */
class GroupTaxonomyAccess implements GroupTaxonomyAccessInterface {

  /**
   * {@inheritdoc}
   */
  public function taxonomyViewAccess(AccountInterface $account, RouteMatch $routeMatch) {
    $taxonomy = $routeMatch->getParameter('taxonomy_vocabulary');
    return \Drupal::service('group_taxonomy.taxonomy')->taxonomyVocabularyAccess('view', $taxonomy, $account);
  }

  /**
   * {@inheritdoc}
   */
  public function taxonomyEditAccess(AccountInterface $account, RouteMatch $routeMatch) {
    $taxonomy = $routeMatch->getParameter('taxonomy_vocabulary');
    return \Drupal::service('group_taxonomy.taxonomy')->taxonomyVocabularyAccess('update', $taxonomy, $account);
  }

  /**
   * {@inheritdoc}
   */
  public function taxonomyDeleteAccess(AccountInterface $account, RouteMatch $routeMatch) {
    $taxonomy = $routeMatch->getParameter('taxonomy_vocabulary');
    return \Drupal::service('group_taxonomy.taxonomy')->taxonomyVocabularyAccess('delete', $taxonomy, $account);
  }

  /**
   * {@inheritdoc}
   */
  public function taxonomyTermAccess(AccountInterface $account, RouteMatch $routeMatch) {
    $term = $routeMatch->getParameter('taxonomy_term');
    if ($term instanceof TermInterface) {
      return \Drupal::service('group_taxonomy.taxonomy')->taxonomyTermAccess('edit', $term, $account);
    }
    return AccessResult::neutral();
  }

}
