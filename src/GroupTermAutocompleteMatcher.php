<?php

namespace Drupal\group_taxonomy;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Tags;
use Drupal\Core\Entity\EntityAutocompleteMatcher;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\GroupMembershipLoader;

/**
 * Matcher class to get autocompletion results for group taxonomy references.
 */
class GroupTermAutocompleteMatcher extends EntityAutocompleteMatcher {

  /**
   * The entity reference selection handler plugin manager.
   *
   * @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface
   */
  protected $selectionManager;

  /**
   * The group membership loader.
   *
   * @var \Drupal\group\GroupMembershipLoader
   */
  protected $membershipLoader;

  /**
   * The group taxonomy service.
   *
   * @var \Drupal\group_taxonomy\GroupTaxonomyService
   */
  protected $groupTaxonomyService;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a GroupTermAutocompleteMatcher object.
   *
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selection_manager
   *   The entity reference selection handler plugin manager.
   * @param \Drupal\group\GroupMembershipLoader $membership_loader
   *   The group membership loader.
   * @param \Drupal\group_taxonomy\GroupTaxonomyService $group_taxonomy_service
   *   The group taxonomy service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(SelectionPluginManagerInterface $selection_manager, GroupMembershipLoader $membership_loader, GroupTaxonomyService $group_taxonomy_service, AccountInterface $current_user) {
    $this->selectionManager = $selection_manager;
    $this->membershipLoader = $membership_loader;
    $this->groupTaxonomyService = $group_taxonomy_service;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function getMatches($target_type, $selection_handler, $selection_settings, $string = '') {

    // We only care about this user is group membership.
    $account = $this->currentUser;
    $group_membership = $this->membershipLoader->loadByUser($account);
    if (!empty($group_membership) && $target_type == 'taxonomy_term') {
      foreach ($selection_settings['target_bundles'] as $vid) {
        $group_taxonomies = $this->groupTaxonomyService->loadUserGroupTaxonomies('view', $account);
        if (!isset($group_taxonomies[$vid])) {
          unset($selection_settings['target_bundles'][$vid]);
        }
      }
    }

    $matches = [];

    $options = $selection_settings + [
      'target_type' => $target_type,
      'handler' => $selection_handler,
    ];
    $handler = $this->selectionManager->getInstance($options);

    if (isset($string)) {
      // Get an array of matching entities.
      $match_operator = !empty($selection_settings['match_operator']) ? $selection_settings['match_operator'] : 'CONTAINS';
      $entity_labels = $handler->getReferenceableEntities($string, $match_operator, 10);

      // Loop through the entities and convert them into autocomplete output.
      foreach ($entity_labels as $values) {
        foreach ($values as $entity_id => $label) {
          $key = "$label ($entity_id)";
          // Strip things like starting/trailing white spaces, line breaks and
          // tags.
          $key = preg_replace('/\s\s+/', ' ', str_replace("\n", '', trim(Html::decodeEntities(strip_tags($key)))));
          // Names containing commas or quotes must be wrapped in quotes.
          $key = Tags::encode($key);
          $matches[] = ['value' => $key, 'label' => $label];
        }
      }
    }

    return $matches;
  }

}
