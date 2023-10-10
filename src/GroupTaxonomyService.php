<?php

namespace Drupal\group_taxonomy;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\GroupMembershipLoader;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\VocabularyInterface;

/**
 * Checks access for displaying taxonomy and term pages.
 */
class GroupTaxonomyService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user's account object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The group membership loader.
   *
   * @var \Drupal\group\GroupMembershipLoader
   */
  protected $membershipLoader;

  /**
   * An array containing the taxonomies for a user.
   *
   * @var array
   */
  protected $userTaxonomies = [];

  /**
   * An array containing the taxonomies for a user and group.
   *
   * @var array
   */
  protected $userGroupTaxomomies = [];

  /**
   * Static cache of all group taxonomy objects keyed by group ID.
   *
   * @var \Drupal\taxonomy\VocabularyInterface[][]
   */
  protected $groupTaxonomies = [];

  /**
   * An array containing the taxonomy access results.
   *
   * @var array
   */
  protected $taxonomyAccess = [];

  /**
   * Constructs a new GroupTypeController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\group\GroupMembershipLoader $membership_loader
   *   The group membership loader.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user, GroupMembershipLoader $membership_loader) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->membershipLoader = $membership_loader;
  }

  /**
   * Check taxonomy vocabulary access.
   *
   * @param string $op
   *   The operation being executed.
   * @param \Drupal\taxonomy\VocabularyInterface $taxonomy
   *   The taxonomy vocabulary being accessed.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account trying to access the term.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultNeutral|mixed
   *   The access result.
   */
  public function taxonomyVocabularyAccess($op, VocabularyInterface $taxonomy, AccountInterface $account = NULL) {
    if (!isset($account)) {
      $account = $this->currentUser;
    }

    if (isset($this->taxonomyAccess[$op][$account->id()][$taxonomy->id()])) {
      return $this->taxonomyAccess[$op][$account->id()][$taxonomy->id()];
    }

    if ($account->hasPermission('administer taxonomy')) {
      return $this->taxonomyAccess[$op][$account->id()][$taxonomy->id()] = AccessResult::allowed();
    }

    $plugin_id = 'group_taxonomy';
    $group_content_types = $this->entityTypeManager->getStorage('group_content_type')
      ->loadByContentPluginId($plugin_id);
    if (empty($group_content_types)) {
      return $this->taxonomyAccess[$op][$account->id()][$taxonomy->id()] = AccessResult::neutral();
    }

    // Load all the group content for this taxonomy.
    $group_contents = $this->entityTypeManager->getStorage('group_content')
      ->loadByProperties([
        'type' => array_keys($group_content_types),
        'entity_id_str' => $taxonomy->id(),
      ]);

    // If the taxonomy does not belong to any group, we have nothing to say.
    if (empty($group_contents)) {
      return $this->taxonomyAccess[$op][$account->id()][$taxonomy->id()] = AccessResult::neutral();
    }

    /** @var \Drupal\group\Entity\GroupInterface[] $groups */
    $groups = [];
    foreach ($group_contents as $group_content) {
      /** @var \Drupal\group\Entity\GroupContentInterface $group_content */
      $group = $group_content->getGroup();
      $groups[$group->id()] = $group;
    }

    // From this point on you need group to allow you to perform the requested
    // operation. If you are not granted access for a group, you should be
    // denied access instead.
    foreach ($groups as $group) {
      if ($group->hasPermission("$op $plugin_id entity", $account)) {
        return $this->taxonomyAccess[$op][$account->id()][$taxonomy->id()] = AccessResult::allowed();
      }
    }

    return $this->taxonomyAccess[$op][$account->id()][$taxonomy->id()] = AccessResult::neutral();
  }

  /**
   * Check access to a taxonomy term according to vocabulary update permission.
   *
   * @param string $op
   *   The operation being executed.
   * @param \Drupal\taxonomy\TermInterface $term
   *   The term being accessed.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account trying to access the term.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultNeutral|mixed
   *   The access result.
   */
  public function taxonomyTermAccess($op, TermInterface $term, AccountInterface $account = NULL) {
    $taxonomy = $this->entityTypeManager->getStorage('taxonomy_vocabulary')
      ->load($term->bundle());

    // The operation to be checked on vocabulary is always 'update'.
    // If user has permission to edit vocabulary then they have permission to
    // add/edit/delete terms to this vocabulary.
    return $this->taxonomyVocabularyAccess('update', $taxonomy, $account);
  }

  /**
   * Load the taxonomies that user has access by operation.
   *
   * @param string $op
   *   The operation being executed.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account trying to access the term.
   *
   * @return mixed
   *   The taxonomies that a user has access for a specific operation.
   */
  public function loadUserGroupTaxonomies($op, AccountInterface $account = NULL) {
    if (!isset($account)) {
      $account = $this->currentUser;
    }

    if (isset($this->userTaxonomies[$op][$account->id()])) {
      return $this->userTaxonomies[$op][$account->id()];
    }

    $group_memberships = $this->membershipLoader->loadByUser($account);
    $this->userTaxonomies[$op][$account->id()] = [];
    foreach ($group_memberships as $group_membership) {
      $this->userTaxonomies[$op][$account->id()] += $this->loadUserGroupTaxonomiesByGroup($op, $group_membership->getGroupContent()->gid->target_id, $account);
    }

    return $this->userTaxonomies[$op][$account->id()];
  }

  /**
   * Load the taxonomies that user has access by operation and group.
   *
   * @param string $op
   *   The operation being executed.
   * @param string $group_id
   *   The group id.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account trying to access the term.
   *
   * @return array|\Drupal\taxonomy\VocabularyInterface[]|mixed
   *   The access result.
   */
  public function loadUserGroupTaxonomiesByGroup($op, $group_id, AccountInterface $account = NULL) {
    if (!isset($account)) {
      $account = $this->currentUser;
    }

    if (isset($this->userGroupTaxomomies[$op][$account->id()][$group_id])) {
      return $this->userGroupTaxomomies[$op][$account->id()][$group_id];
    }

    $group_taxonomies = $this->getGroupTaxonomies();
    $group_taxonomy_for_group = (!empty($group_taxonomies[$group_id])) ? $group_taxonomies[$group_id] : [];

    return $this->userGroupTaxomomies[$op][$account->id()][$group_id] = $group_taxonomy_for_group;
  }

  /**
   * Get all group taxonomy objects.
   *
   * We create a static cache of group taxonomies since loading them
   * individually has a big impact on performance.
   *
   * @return \Drupal\taxonomy\VocabularyInterface[][]
   *   A nested array containing all group taxonomy objects keyed by group ID.
   */
  public function getGroupTaxonomies() {
    if (!$this->groupTaxonomies) {
      $plugin_id = 'group_taxonomy';

      $taxonomies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')
        ->loadMultiple();

      $group_content_types = $this->entityTypeManager->getStorage('group_content_type')
        ->loadByContentPluginId($plugin_id);
      if (!empty($group_content_types)) {
        $group_contents = $this->entityTypeManager->getStorage('group_content')
          ->loadByProperties(['type' => array_keys($group_content_types)]);

        foreach ($group_contents as $group_content) {
          /** @var \Drupal\group\Entity\GroupContentInterface $group_content */
          $this->groupTaxonomies[$group_content->getGroup()->id()][$group_content->getEntity()->id()] = $taxonomies[$group_content->getEntity()->id()];
        }
      }
    }

    return $this->groupTaxonomies;
  }

}
