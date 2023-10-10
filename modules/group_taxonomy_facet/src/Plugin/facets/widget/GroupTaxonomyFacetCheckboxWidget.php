<?php

namespace Drupal\group_taxonomy_facet\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\Plugin\facets\widget\CheckboxWidget;
use Drupal\facets\FacetInterface;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * The checkbox / radios widget.
 *
 * @FacetsWidget(
 *   id = "group_taxonomy_facet_checkbox_widget",
 *   label = @Translation("Group Vocab List of checkboxes"),
 *   description = @Translation("A Group Vocab configurable widget that shows a list of checkboxes"),
 * )
 */
class GroupTaxonomyFacetCheckboxWidget extends CheckboxWidget {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'group_facet_id' => '',
      ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {
    $form = parent::buildConfigurationForm($form, $form_state, $facet);
    $form['group_facet_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The group facet ID.'),
      '#default_value' => $this->getConfiguration()['group_facet_id'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    $build = parent::build($facet);
    $query_results = $this->getTermsInformation($facet);

    $excluded_vocabs = [];
    if (isset($facet->getProcessorConfigs()['exclude_vocabulary'])) {
      $excluded_vocabs = explode(PHP_EOL, str_replace("\r", "", $facet->getProcessorConfigs()['exclude_vocabulary']['settings']['exclude']));
    }
    $allowed_vocabs = [];

    if (isset($this->configuration['group_facet_id'])) {
      $group_facet_id = $this->configuration['group_facet_id'];
      $group_facet = \Drupal::entityTypeManager()->getStorage('facets_facet')->load($group_facet_id);
      $facets_manager = \Drupal::getContainer()->get('facets.manager');
      $group_facet = $facets_manager->returnProcessedFacet($group_facet);
      $active_items = $group_facet->getActiveItems();
      $group_name = reset($active_items);
      if ($group_name) {
        $group = \Drupal::entityTypeManager()->getStorage('group')->loadByProperties(['label' => $group_name]);
        $group = reset($group);
        if ($group) {
          $taxonomies = $group->getContent('group_taxonomy');
          $allowed_vocabs = [];
          foreach ($taxonomies as $groupContent) {
            $allowed_vocabs[] = $groupContent->getEntity()->id();
          }
        }
      }

    }

    $final_array = [];
    foreach ($query_results as $query_result) {
      if (Vocabulary::load($query_result->vid)) {
        // Limit vocabs based on the selected group.
        if ($allowed_vocabs && !in_array(Vocabulary::load($query_result->vid)->id(), $allowed_vocabs)) {
          continue;
        }
        // Exclude vocabs.
        if ($excluded_vocabs && in_array(Vocabulary::load($query_result->vid)->id(), $excluded_vocabs)) {
          continue;
        }
        $final_array[Vocabulary::load($query_result->vid)->label()][$query_result->tid] = [
          'tid' => $query_result->tid,
          'name' => $query_result->name,
          'url' => $this->getItemFromItemsSet($build['#items'], $query_result->tid),
        ];
        $final_array[Vocabulary::load($query_result->vid)->label()][$query_result->tid]['url']['#title']['#value'] = $query_result->name;
      }
    }
    $vocabs = array_keys($final_array);
    $build['#items'] = [];
    foreach ($vocabs as $vocab) {
      $build['#items'][$vocab] = [
        '#type' => 'markup',
        '#markup' => $vocab,
        '#tree' => TRUE,
      ];
      $sublinks = [];
      foreach ($final_array[$vocab] as $value) {
        $sublinks[] = $value['url'];
      }
      $build['#items'][$vocab]['subitem'] = $sublinks;
    }
    if (isset($build['#items']) and empty($build['#items'])) {
      $build['#facet']->setResults([]);
    }

    return $build;
  }

  /**
   * Gets taxonomy information based on facet results.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   *
   * @return mixed
   *   An array with tid, vid, name of terms assosiated with facet results.
   */
  public function getTermsInformation(FacetInterface $facet) {
    $termsId = [];
    foreach ($facet->getResults() as $result) {
      $termsId[] = $result->getRawValue();
    }

    $query = \Drupal::database()->select('taxonomy_term_field_data', 't')
      ->fields('t', ['tid', 'vid', 'name']);
    if ($termsId) {
      $query->condition('t.tid', $termsId, 'IN');
    }
    return $query->execute()->fetchAll();
  }

  /**
   * Helper function that gets correct facets result item, based on taxonomy id.
   *
   * @param array $items
   *   The items array.
   * @param int $termId
   *   The id of the taxonomy term.
   *
   * @return mixed
   *   The result item.
   */
  public function getItemFromItemsSet(array $items, $termId) {
    foreach ($items as $resultId => $result) {
      if ($result['#title']['#raw_value'] == $termId) {
        return $items[$resultId];
      }
    }
  }

}
