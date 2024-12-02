<?php

namespace Drupal\custom_headline_filter\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\ResultRow;

/**
 * Filters out headlines that are similar to ones already displayed.
 *
 * @ViewsFilter("similar_headline_filter")
 */
class SimilarHeadlineFilter extends FilterPluginBase {

  protected $alwaysMultiple = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['similarity_threshold'] = ['default' => 80];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['similarity_threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Similarity threshold'),
      '#description' => $this->t('Headlines with similarity above this threshold will be filtered out (0-100).'),
      '#default_value' => $this->options['similarity_threshold'] ?? 80,
      '#min' => 0,
      '#max' => 100,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // \Drupal::logger('custom_headline_filter')->notice('Query method started');
  }

  /**
   * {@inheritdoc}
   */
  public function validate() {
    // \Drupal::logger('custom_headline_filter')->notice('Validate method called');
    return parent::validate();
  }

  /**
   * Filter similar headlines after the query has been executed.
   */
  public function filterSimilarHeadlines() {
    // \Drupal::logger('custom_headline_filter')->notice('filterSimilarHeadlines started');

    // Debug the view object
    // \Drupal::logger('custom_headline_filter')->notice('View details: @view', [
    //   '@view' => print_r([
    //     'name' => $this->view->id(),
    //     'current_display' => $this->view->current_display,
    //     'result_count' => count($this->view->result),
    //   ], TRUE),
    // ]);

    if (empty($this->view->result)) {
      // \Drupal::logger('custom_headline_filter')->notice('No results to process');
      return;
    }

    $results = $this->view->result;
    $filtered_results = [];
    $threshold = $this->options['similarity_threshold'] ?? 80;

    // \Drupal::logger('custom_headline_filter')->notice('Processing @count results with threshold @threshold', [
    //   '@count' => count($results),
    //   '@threshold' => $threshold,
    // ]);

    // Get a sample of what's in the first result
    if (!empty($results)) {
      $first_row = reset($results);
      // \Drupal::logger('custom_headline_filter')->notice('First row properties: @props', [
      //   '@props' => print_r(get_object_vars($first_row), TRUE),
      // ]);
    }

    foreach ($results as $index => $row) {
      $current_title = $this->getTitleFromRow($row);
      // \Drupal::logger('custom_headline_filter')->notice('Processing row @index with title: @title', [
      //   '@index' => $index,
      //   '@title' => $current_title,
      // ]);

      $is_similar = false;

      foreach ($filtered_results as $filtered_row) {
        $filtered_title = $this->getTitleFromRow($filtered_row);
        $similarity = $this->calculateSimilarity($current_title, $filtered_title);

        if ($similarity >= $threshold) {
          $is_similar = true;
          \Drupal::logger('custom_headline_filter')->notice('Found similar title: "@title1" matches "@title2" with @similarity%', [
            '@title1' => $current_title,
            '@title2' => $filtered_title,
            '@similarity' => $similarity,
          ]);
          break;
        }
      }

      if (!$is_similar) {
        $filtered_results[] = $row;
      }

      if (count($filtered_results) >= 20) {
        // \Drupal::logger('custom_headline_filter')->notice('Reached 15 items, stopping');
        break;
      }
    }

    // \Drupal::logger('custom_headline_filter')->notice('Filtered from @original to @filtered results', [
    //   '@original' => count($results),
    //   '@filtered' => count($filtered_results),
    // ]);

    $this->view->result = $filtered_results;
  }

  /**
   * Extract the title from a result row.
   */
  protected function getTitleFromRow(ResultRow $row) {
    // Debug what fields are available
    $fields = array_keys(get_object_vars($row));
    // \Drupal::logger('custom_headline_filter')->notice('Available fields in row: @fields', [
    //   '@fields' => implode(', ', $fields),
    // ]);

    // Try to get field values if entity field API is being used
    if (isset($row->_entity)) {
      // \Drupal::logger('custom_headline_filter')->notice('Entity type: @type', [
      //   '@type' => get_class($row->_entity),
      // ]);
      return $row->_entity->label();
    }

    // Log if we can't find the title
    \Drupal::logger('custom_headline_filter')->error('Unable to find title in row');
    return '';
  }

  /**
   * Calculate similarity between two strings.
   */
  protected function calculateSimilarity($string1, $string2) {
    if (empty($string1) || empty($string2)) {
      // \Drupal::logger('custom_headline_filter')->error('Empty string provided for comparison');
      return 0;
    }

    // If strings are identical, return 100
    if ($string1 === $string2) {
      return 100;
    }

    // Convert to lowercase and remove non-alphanumeric characters
    $string1 = preg_replace('/[^a-z0-9]+/', '', strtolower($string1));
    $string2 = preg_replace('/[^a-z0-9]+/', '', strtolower($string2));

    similar_text($string1, $string2, $percent);
    return $percent;
  }
}
