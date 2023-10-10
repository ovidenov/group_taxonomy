<?php

namespace Drupal\group_taxonomy\Plugin\GroupContentEnabler;

use Drupal\group\Plugin\GroupContentEnablerBase;

/**
 * Allows Taxonomy vocabulary to be added to groups.
 *
 * @GroupContentEnabler(
 *   id = "group_taxonomy",
 *   label = @Translation("Group taxonomy"),
 *   entity_access = TRUE,
 *   description = @Translation("Adds taxonomy vocabulary to groups."),
 *   entity_type_id = "taxonomy_vocabulary",
 * )
 */
class GroupTaxonomy extends GroupContentEnablerBase {

  /**
   * {@inheritdoc}
   */
  public function getPermissions() {
    $plugin_id = $this->getPluginId();

    // Allow permissions here and in child classes to easily use the plugin name
    // and target entity type name in their titles and descriptions.
    $title_args = [
      '%entity_type' => $this->getEntityType()->getSingularLabel(),
    ];
    $defaults = [
      'title_args' => $title_args,
      'description_args' => $title_args,
    ];

    // Use the same title prefix to keep permissions sorted properly.
    $entity_prefix = 'Entity:';
    $relation_prefix = 'Relationship:';

    $permissions["view $plugin_id entity"] = [
      'title' => "$entity_prefix View %entity_type entities",
    ] + $defaults;
    $permissions["create $plugin_id entity"] = [
      'title' => "$entity_prefix Add %entity_type entities",
      'description' => 'Allows you to create a new %entity_type entity and relate it to the group.',
    ] + $defaults;
    $permissions["update $plugin_id entity"] = [
      'title' => "$entity_prefix Edit %entity_type entities",
    ] + $defaults;
    $permissions["delete $plugin_id entity"] = [
      'title' => "$entity_prefix Delete %entity_type entities",
    ] + $defaults;
    $permissions["view $plugin_id content"] = [
      'title' => "$relation_prefix View entity relations",
    ] + $defaults;
    $permissions["create $plugin_id content"] = [
      'title' => "$relation_prefix Add entity relation",
      'description' => 'Allows you to relate an existing %entity_type entity to the group.',
    ] + $defaults;
    $permissions["update $plugin_id content"] = [
      'title' => "$relation_prefix Edit entity relations",
    ] + $defaults;
    $permissions["delete $plugin_id content"] = [
      'title' => "$relation_prefix Delete entity relations",
    ] + $defaults;

    return $permissions;
  }

}
