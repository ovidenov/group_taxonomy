<?php

namespace Drupal\group_taxonomy_facet\Plugin\facets\processor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;

/**
 * Provides a processor that excludes specified items.
 *
 * @FacetsProcessor(
 *   id = "exclude_vocabulary",
 *   label = @Translation("Exclude vocabulary"),
 *   description = @Translation("Exclude vocabulary."),
 *   stages = {
 *     "build" = 50
 *   }
 * )
 */
class ExcludeVocabulary extends ProcessorPluginBase implements BuildProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {
    $config = $this->getConfiguration();

    $build['exclude'] = [
      '#title' => $this->t('Exclude items'),
      '#type' => 'textarea',
      '#default_value' => $config['exclude'],
      '#description' => $this->t("Separate by new line."),
    ];


    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'exclude' => [],
    ];
  }

}
