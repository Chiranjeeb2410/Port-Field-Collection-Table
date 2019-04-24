<?php

/**
 * @file
 * Contains \Drupal\field_collection_table\Plugin\Field\FieldFormatter\FieldCollectionTableView.
 */

namespace Drupal\field_collection_table\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;

/**
 * @FieldFormatter(
 *  id = "field_collection_table_view",
 *  label = @Translation("Table of field collection items"),
 *  field_types = {"field_collection"}
 * )
 */
class FieldCollectionTableView extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    /**
     * Get config of field to get order of fields
     *
     * TODO : make default configurable through settings.
     */
     $field_collection_field = $this->fieldDefinition->getName();
     $key = 'core.entity_view_display.field_collection_item.'.$field_collection_field.'.default';
     $content = \Drupal::config($key)->get('content');

     /**
     * Loop all items and get field labels and data.
     */
     foreach ($items as $delta => $item) {
       if($field_collection_item = $item->getFieldCollectionItem())  {
         $row = [];
         foreach ($field_collection_item->getFieldDefinitions() as $fieldname =>
          $field_definition) {
            if(isset($content[$fieldname]) && $field_definition instanceof FieldConfig) {
              $weight = $content[$fieldname]['weight'];
              if(!isset($header[$weight]))  {
                $header[$weight] = $field_definition->getLabel();
                $content[$fieldname]['label'] = 'hidden';
                $formatters[$fieldname] = \Drupal::service('plugin.manager.field.formatter')->getInstance(array(
                'field_definition' => $field_definition,
                'view_mode' => 'default',
                'configuration' => $content[$fieldname],
              ));
              }
              $formatter = $formatters[$fieldname];
              $entities = $field_collection_item->{$fieldname};
              $formatter->prepareView(array($entities));
              $build = $formatter->view($field_collection_item->{$fieldname});

              $row[$weight] = render($build);
            }
         }
         ksort($row);
         $rows[] = $row;
       }
     }
     ksort($header);

     $table = [
       '#type' => 'table',
       '#headers' => $header,
       '#rows' => $row,
     ];

     return ['#markup' => render($table)];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    $element = [];
    $field_options = ['none' => $this->t('None')];
    $view_modes = $this->getConfigurableViewModes();

    if (!empty($view_modes)) {
      $form['view_mode'] = [
        '#title' => t('View Mode'),
        '#description' => t('Select the view mode which will control which fields are shown and the display settings of those fields.'),
        '#type' => 'select',
        '#default_value' => $this->getSetting('view_mode'),
        '#options' => $this->getConfigurableViewModes(),
      ];
    }

    $element['hide_empty'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide empty collection'),
      '#default_value' => $this->getSetting('hide_empty'),
      '#description' => $this->t('If enabled, nothing will be displayed for an empty collection (not even the add link).'),
    ];

    $element['empty'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide empty columns'),
      '#description' => $this->t('If checked, hides empty table columns.'),
      '#default_value' => $this->getSetting('empty'),
    ];

    $element['caption'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Table caption'),
      '#description' => $this->t('Displayed in the caption element above the table'),
      '#default_value' => $this->getSetting('caption'),
    ];

    $element['orientation'] = [
      '#type' => 'select',
      '#title' => $this->t('Orientation'),
      '#description' => $this->t('Set the orientation of the table'),
      '#options' => [
        'columns' => $this->t('Columns'),
        'rows' => $this->t('Rows'),
      ],
      '#default_value' => $this->getSetting('orientation'),
    ];

    $element['header_column'] = [
      '#type' => 'select',
      '#title' => $this->t('Header field'),
      '#description' => $this->t('The selected field value will be used as the horizontal table header'),
      '#options' => $field_options,
      '#states'=> ['visible' => [':input[name="fields[field_fc]
      [settings_edit_form][settings][orientation]"]' => ['value' => 'rows']]],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSettings() {
    return [
      'view_mode' => 'default',
      'hide_empty' => FALSE,
      'empty' => FALSE,
      'caption' => '',
      'orientation' => 'default',
      'header_column' => 'default',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary()  {

    $output = settingsSummary();

    $output .= '<br>';
    $output .= !empty($this->getSetting('hide_empty')) ? $this->t('Empty collections: Hidden') : $this->t('Empty collections: Shown');
    $output .= '<br>';
    $output .= !empty($this->getSetting('empty')) ? $this->t('Empty columns: Hidden') : $this->t('Empty columns: Shown');
    $output .= !empty($this->getSetting('caption')) ? '<br>' . $this->t('Caption: %caption', ['%caption' => $this->t($this->getSetting('caption'))]) : '';
    $orientations = ['columns' => $this->t('Column'), 'rows' => $this->t('Row')];
    $output .= '<br />';
    $output .= !empty($this->getSetting('empty')) ? $this->t('Empty columns: Hidden') : $this->t('Empty columns: Shown');
    if (!empty($this->getSetting('orientation'))) {
      $output .= '<br />';
      $output .= $this->t('Format fields as <strong>!orientation</strong>.', ['!orientation' => $orientations[$this->getSetting('orientation')]]);
    }
    if (!empty($this->getSetting('orientation')) && $this->getSetting('orientation') === 'rows') {
      $output .= '<br />';
      if (!empty($this->getSetting('header_column')) && $this->getSetting('header_column') !== 'none') {
        $output .= '<br />';
        $output .= $this->t('Field @field value is used as the header', ['@field' => $this->getSetting('header_column')]);
      }
    }

    return $output;
  }

}
