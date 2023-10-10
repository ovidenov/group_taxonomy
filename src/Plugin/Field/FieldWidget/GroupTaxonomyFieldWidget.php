<?php

namespace Drupal\group_taxonomy\Plugin\Field\FieldWidget;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\ResettableStackedRouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\Group;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\group\Entity\GroupContent;

/**
 * Plugin implementation of the 'group_vocab_field_widget' widget.
 *
 * @FieldWidget(
 *   id = "group_taxonomy_field_widget",
 *   module = "group_taxonomy",
 *   label = @Translation("Group Taxonomy Field Widget"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class GroupTaxonomyFieldWidget extends WidgetBase implements ContainerFactoryPluginInterface {

	/**
	 * The current route match.
	 *
	 * @var \Drupal\Core\Routing\ResettableStackedRouteMatchInterface
	 */
	protected $route;

	/**
	 * The database connection.
	 *
	 * @var \Drupal\Core\Database\Connection
	 */
	protected $database;


	/**
	 * The entity type manager.
	 *
	 * @var \Drupal\Core\Entity\EntityTypeManagerInterface
	 */
	protected $entityTypeManager;

	/**
	 * The module handler.
	 *
	 * @var \Drupal\Core\Extension\ModuleHandlerInterface
	 */
	protected $moduleHandler;

	/**
	 * The account.
	 *
	 * @var \Drupal\Core\Session\AccountProxyInterface
	 */
	protected $account;

	/**
	 * The group membership loader service.
	 *
	 * @var \Drupal\group\GroupMembershipLoaderInterface
	 */
	protected $membershipLoader;

	/**
	 * The currently loaded entity.
	 *
	 * @var \Drupal\Core\Entity\EntityInterface
	 */
	public $loadedEntity;

	/**
	 * Is the currently loaded entity new.
	 *
	 * @var bool
	 */
	public $isNewEntity;

	/**
	 * {@inheritdoc}
	 */
	public function __construct(
		$plugin_id,
		$plugin_definition,
		FieldDefinitionInterface $field_definition,
		array $settings,
		array $third_party_settings,
		ResettableStackedRouteMatchInterface $route,
		Connection $database,
		EntityTypeManagerInterface $entityTypeManager,
		ModuleHandlerInterface $moduleHandler,
		AccountProxyInterface $account,
		GroupMembershipLoaderInterface $membershipLoader
	)
	{
		parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
		$this->route = $route;
		$this->database = $database;
		$this->entityTypeManager = $entityTypeManager;
		$this->moduleHandler = $moduleHandler;
		$this->account = $account;
		$this->membershipLoader = $membershipLoader;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
	{
		return new static(
			$plugin_id,
			$plugin_definition,
			$configuration['field_definition'],
			$configuration['settings'],
			$configuration['third_party_settings'],
			$container->get('current_route_match'),
			$container->get('database'),
			$container->get('entity_type.manager'),
			$container->get('module_handler'),
			$container->get('current_user'),
			$container->get('group.membership_loader'),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function handlesMultipleValues()
	{
		return TRUE;
	}

	/**
	 * {@inheritdoc}
	 */
	public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
	{

    if ($form_state->getFormObject() instanceof EntityFormInterface) {
      $this->loadedEntity = $form_state->getFormObject()->getEntity();
      $this->isNewEntity = $form_state->getFormObject()->getEntity()->isNew();
    }

    if (!$this->getGroups()) {
      return;
    }

    $options = $this->getOptions();
    $selected_options = $this->getSelectedOptions();

    $useChosen = FALSE;
    // If Chosen is enabled, use it.
    if ($this->moduleHandler->moduleExists('chosen')) {
      $useChosen = TRUE;
    }
    $current_user_groups = $this->membershipLoader->loadByUser($this->account->getAccount());

    foreach ($options as $key => $values) {
      $defaultValue = array_keys(array_intersect_key($values, $selected_options));
      $element[$key] = [
        '#type' => 'select',
        '#title' => Vocabulary::load($key)->label(),
        '#options' => $values,
        '#default_value' => $defaultValue,
        '#multiple' => TRUE,
        '#chosen' => $useChosen,
      ];

      $group_taxonomy_relationship = \Drupal::entityTypeManager()->getStorage('group_content')->loadByProperties(['entity_id_str' => $key]);
      $group_taxonomy_relationship = reset($group_taxonomy_relationship);
      $group_taxonomy_group = $group_taxonomy_relationship->getGroup();

      if (!$this->account->hasPermission('group taxonomy widget see all groups')) {
        // Make sure that user from KS1 doesn't see vocabularies from KS2
        $remove_current_group = TRUE;
        foreach ($current_user_groups as $current_user_group) {
          if ($current_user_group->getGroup() === $group_taxonomy_group) {
            $remove_current_group = FALSE;
          }
        }
        if ($remove_current_group) {
          $element[$key]['#disabled'] = TRUE;
        }
      }

    }

    return ['value' => $element];
	}

	/**
	 * {@inheritdoc}
	 */
	public function massageFormValues(array $values, array $form, FormStateInterface $form_state)
	{
		// We need to change the structure.
		$array = $values['value'];
		$values = [];
		foreach ($array as $data) {
			foreach ($data as $row) {
				$values[]['target_id'] = $row;
			}
		}
		return $values;
	}

	/**
	 * Get options.
	 *
	 * @return array|false
	 *   An array of options.
	 */
	public function getOptions()
	{
		$options = [];
		$allowedTaxonomies = [];

		foreach ($this->getGroups() as $group) {
			$taxonomyGroupContents = $group->getContent('group_taxonomy');
			// Filter based on the current content type and vocabulary settings.
			foreach ($taxonomyGroupContents as $groupContent) {
				$allowedContentTypes = [];
				foreach ($groupContent->get('gt_allowed_content_types')->referencedEntities() as $nodeType) {
					$allowedContentTypes[] = $nodeType->id();
				}
				if (in_array($this->loadedEntity->bundle(), $allowedContentTypes)) {
					$allowedTaxonomies[$group->id()][] = $groupContent->getEntity();
				}
			}

		}

		foreach ($allowedTaxonomies as $groupVocabs) {
			foreach ($groupVocabs as $vocab) {
				// Loads terms from the given vocabulary.
				$terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vocab->id());
				foreach ($terms as $term) {
					$options[$vocab->id()][$term->tid] = $term->name;
				}
			}
		}

		return $options;
	}

	/**
	 * Get's selected options.
	 *
	 * @return array|false
	 *   An array of selected options.
	 */
	public function getSelectedOptions()
	{
		// Gets current node.
		$selectedOptions = [];
		if ($this->loadedEntity && !$this->isNewEntity) {
			// Gets the table for this field.
			$table = $this->fieldDefinition->getFieldStorageDefinition()->getName();
			$selectedOptions = $this->database->select("node__$table", 't')
				->fields('t', [$table . '_target_id'])
				->condition('t.entity_id', $this->loadedEntity->id())
				->execute()->fetchCol(0);
		}
		return array_combine($selectedOptions, $selectedOptions);
	}

	/**
	 * Returns all groups associated with the current node.
	 *
	 * @return array|\Drupal\Core\Entity\EntityInterface[]
	 *   An array of group objects. Can be empty.
	 */

	/**
	 * Returns all groups associated with the current node.
	 *
	 * @param object $form_state
	 *   Form state.
	 *
	 * @return array|\Drupal\Core\Entity\EntityInterface[]
	 *   Array of group entities.
	 */
	public function getGroups($form_state = NULL)
	{
		$current_user_groups = $this->membershipLoader->loadByUser($this->account->getAccount());
		if ($this->isNewEntity) {
			$routes = [
				'entity.group_content.create_form',
				'entity.group_content.create_page',
			];
			$route_name = $this->route->getRouteName();

			// If we are on a page where we add a new node from a group.
			if (in_array($route_name, $routes)) {
				$groupId = $this->route->getParameters()->get('group')->id();
				return Group::loadMultiple([$groupId]);
			}
		}

		if (!$this->isNewEntity) {
			$groupContents = GroupContent::loadByEntity($this->loadedEntity);
			$groups = [];
			foreach ($groupContents as $groupContent) {
				$groups[] = $groupContent->getGroup();
			}

			return $groups;
		}

		return [];
	}

}
