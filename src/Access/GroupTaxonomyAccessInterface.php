<?php

namespace Drupal\group_taxonomy\Access;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Routing\RouteMatch;

/**
 * Provides an interface defining custom access for Taxonomy and Term pages.
 */
interface GroupTaxonomyAccessInterface {

  /**
   * A custom access check for taxonomy overview page.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\Core\Routing\RouteMatch $routeMatch
   *   The route match object.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function taxonomyViewAccess(AccountInterface $account, RouteMatch $routeMatch);

  /**
   * A custom access check for taxonomy edit page.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\Core\Routing\RouteMatch $routeMatch
   *   The route match object.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function taxonomyEditAccess(AccountInterface $account, RouteMatch $routeMatch);

  /**
   * A custom access check for taxonomy delete page.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\Core\Routing\RouteMatch $routeMatch
   *   The route match object.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function taxonomyDeleteAccess(AccountInterface $account, RouteMatch $routeMatch);

  /**
   * A custom access check for taxonomy term add/edit/delete pages.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\Core\Routing\RouteMatch $routeMatch
   *   The route match object.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function taxonomyTermAccess(AccountInterface $account, RouteMatch $routeMatch);

}
