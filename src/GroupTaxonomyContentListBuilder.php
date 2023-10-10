<?php

namespace Drupal\group_taxonomy;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\group\Entity\Controller\GroupContentListBuilder;
use Drupal\group\Entity\GroupContentType;

/**
 * Provides a list controller for taxonomy entities in a group.
 */
class GroupTaxonomyContentListBuilder extends GroupContentListBuilder {

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery();
    $query->sort($this->entityType->getKey('id'));

    // Only show group content for the group on the route.
    $query->condition('gid', $this->group->id());

    // Filter by group taxonomy plugins.
    $plugin_id = 'group_taxonomy';

    $group_content_types = GroupContentType::loadByContentPluginId($plugin_id);
    if (!empty($group_content_types)) {
      $query->condition('type', array_keys($group_content_types), 'IN');
    }

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }

    return $query->accessCheck(TRUE)->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'id' => $this->t('ID'),
      'label' => $this->t('Taxonomy Vocabulary'),
    ];
    $row = $header + parent::buildHeader();

    // Remove plugin and entity types columns.
    unset($row['entity_type'], $row['plugin']);

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\group\Entity\GroupContentInterface $entity */
    $row['id'] = $entity->id();
    $row['label']['data'] = $entity->getEntity()->toLink(NULL, 'edit-form');
    $row = $row + parent::buildRow($entity);

    // Remove plugin and entity types data.
    unset($row['entity_type'], $row['plugin']);

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $options = ['attributes'=> ['class' => 'button button-action button--primary button--small']];
    $group = \Drupal::service('current_route_match')->getParameter('group');
    if (\Drupal::currentUser()->hasPermission('view control buttons on group taxonomy overview page')) {
      $url_new_vocab = Url::fromUserInput('/group/' . $group->id() . '/content/create/group_taxonomy', $options);
      $link_new_vocab =  Link::fromTextAndUrl('Add new vocabulary', $url_new_vocab)->toString();

      $url_existing_vocab = Url::fromUserInput('/group/' . $group->id() . '/content/add/group_taxonomy', $options);
      $link_existing_vocab =  Link::fromTextAndUrl('Add existing vocabulary', $url_existing_vocab)->toString();
      $build ['group_taxonomy_add_new_vocabulary'] = array(
        '#markup' => $link_new_vocab . $link_existing_vocab . '<br><br>',
        '#weight' => -100,
      );
    }
    $build['table']['#empty'] = $this->t("There are no taxonomies related to this group yet.");
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    /** @var \Drupal\group\Entity\GroupContentInterface $entity */
    $operations = parent::getDefaultOperations($entity);

    // Add view operation for the Group Content Relation.
    if (!isset($operations['view']) && $entity->access('view')) {
      $operations['view'] = [
        'title' => $this->t('View relation'),
        'weight' => 1,
        'url' => $entity->toUrl(),
      ];
    }

    // Add operations to view, edit and delete the actual entity.
    if ($entity->getEntity()->access('view')) {
      $operations['view-entity'] = [
        'title' => $this->t('List terms'),
        'weight' => 103,
        'url' => $entity->getEntity()->toUrl('overview-form'),
      ];
    }
    if ($entity->getEntity()->access('update')) {
      $operations['edit-entity'] = [
        'title' => $this->t('Edit taxonomy'),
        'weight' => 104,
        'url' => $entity->getEntity()->toUrl('edit-form'),
      ];
    }
    if ($entity->getEntity()->access('delete')) {
      $operations['delete-entity'] = [
        'title' => $this->t('Delete taxonomy'),
        'weight' => 105,
        'url' => $entity->getEntity()->toUrl('delete-form'),
      ];
    }

    return $operations;
  }

}
