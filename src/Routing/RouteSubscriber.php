<?php

namespace Drupal\group_taxonomy\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * A route subscriber to set custom access to taxonomy routes.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    $routes = $collection->all();
    foreach ($routes as $route_name => $route) {
      switch ($route_name) {

        case 'entity.taxonomy_vocabulary.overview_form':
          $route->setRequirements(['_custom_access' => '\Drupal\group_taxonomy\Access\GroupTaxonomyAccess::taxonomyViewAccess']);
          break;

        case 'entity.taxonomy_vocabulary.edit_form':
        case 'entity.taxonomy_term.add_form':
          $route->setRequirements(['_custom_access' => '\Drupal\group_taxonomy\Access\GroupTaxonomyAccess::taxonomyEditAccess']);
          break;

        case 'entity.taxonomy_vocabulary.delete_form':
          $route->setRequirements(['_custom_access' => '\Drupal\group_taxonomy\Access\GroupTaxonomyAccess::taxonomyDeleteAccess']);
          break;

        case 'entity.taxonomy_term.edit_form':
        case 'entity.taxonomy_term.delete_form':
          $route->setRequirements(['_custom_access' => '\Drupal\group_taxonomy\Access\GroupTaxonomyAccess::taxonomyTermAccess']);
          break;

      }
    }
  }

}
