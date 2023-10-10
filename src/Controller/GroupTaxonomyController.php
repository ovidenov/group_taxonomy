<?php

namespace Drupal\group_taxonomy\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\Controller\GroupContentController;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Plugin\GroupContentEnablerManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for 'Taxonomy' tab/route.
 */
class GroupTaxonomyController extends GroupContentController {

  /**
   * The group type to use in this controller.
   *
   * @var \Drupal\group\Entity\GroupTypeInterface
   */
  protected $groupType;

  /**
   * The group content plugin manager.
   *
   * @var \Drupal\group\Plugin\GroupContentEnablerManagerInterface
   */
  protected $pluginManager;

  /**
   * Constructs a new GroupTaxonomyController.
   *
   * @param \Drupal\group\Plugin\GroupContentEnablerManagerInterface $plugin_manager
   *   The group content plugin manager.
   */
  public function __construct(GroupContentEnablerManagerInterface $plugin_manager) {
    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.group_content_enabler')
    );
  }

  /**
   * Checks access on Group Taxonomies list.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group being accessed.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, GroupInterface $group) {
    return AccessResult::allowedIf(
      $group->hasPermission('access group_taxonomy overview', $account) &&
      $this->isTaxonomyContentEnablerInstalled($group)
    );
  }

  /**
   * Check if taxonomy content enabler plugin is installed on a group type.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group being checked.
   *
   * @return bool
   *   Wheter group_taxonomy plugin is installed or not.
   */
  private function isTaxonomyContentEnablerInstalled(GroupInterface $group) {
    $installed_plugins = $this->pluginManager->getInstalledIds($group->getGroupType());
    return in_array('group_taxonomy', $installed_plugins);
  }

  /**
   * Create a list of Taxonomies in the group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The current group.
   *
   * @return mixed
   *   Renderable list of taxonomies that belongs to the group.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function groupContentOverview(GroupInterface $group) {
    $class = '\Drupal\group_taxonomy\GroupTaxonomyContentListBuilder';
    $definition = $this->entityTypeManager()->getDefinition('group_content');
    return $this->entityTypeManager()->createHandlerInstance($class, $definition)->render();
  }

  /**
   * Title for the Taxonomies list overview.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The current group.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The page title.
   */
  public function groupContentOverviewTitle(GroupInterface $group) {
    return $this->t("%label taxonomies", ['%label' => $group->label()]);
  }

}
