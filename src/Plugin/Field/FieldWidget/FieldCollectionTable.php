<?php

/**
 * @file
 * Contains \Drupal\field_collection_table\Plugin\Field\FieldWidget\FieldCollectionTable.
 */

namespace Drupal\field_collection_table\Plugin\Field\FieldWidget;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\field_collection\Entity\FieldCollectionItem;
use Drupal\Core\Render\Element;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;

/**
 * @FieldWidget(
 *  id = "field_collection_table",
 *  label = @Translation("Table"),
 *  field_types = {"field_collection"}
 * )
 */
class FieldCollectionTable extends WidgetBase implements WidgetInterface  {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'nodragging' => FALSE,
      'hide_title' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    $element = [];

    $element['nodragging'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable drag and drop'),
      '#description' => $this->t('If checked, users cannot rearrange the rows.'),
      '#default_value' => $this->getSetting('nodragging'),
      '#weight' => 1,
    ];

    $element['hide_title'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide title'),
      '#description' => $this->t('If checked, the field title will be hidden.'),
      '#default_value' => $this->getSetting('hide_title'),
      '#weight' => 2,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element,
    array &$form, FormStateInterface $form_state)  {

    //building the form using field_collection_embed and not calling the same
    //TODO: alter formElement by calling field_collection_embed instead
    $field_name = $this->fieldDefinition->getName();

    $parents = array_merge($element['#field_parents'], array($field_name, $delta));

    $element += [
      '#element_validate' => [[static::class, 'validate']],
      '#parents' => $parents,
      '#field_name' => $field_name,
    ];

    if ($this->fieldDefinition->getFieldStorageDefinition()->getCardinality() == 1) {
      $element['#type'] = 'fieldset';
    }

    $field_state = static::getWidgetState($element['#field_parents'], $field_name, $form_state);

    $display = \Drupal::service('entity_display.repository')
      ->getFormDisplay('field_collection_item', $field_name)
      ->setComponent($field_name, [
        'type' => 'text_textfield',
      ]);
    save();
    $display->buildForm($field_collection_item, $element, $form_state);

    if (empty($element['#required'])) {
      $element['#after_build'][] = [static::class, 'delayRequiredValidation'];
      $form['#attributes']['novalidate'] = 'novalidate';
    }

    if ($this->fieldDefinition->getFieldStorageDefinition()->getCardinality() == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
      $options = ['query' => ['element_parents' => implode('/', $element['#parents'])]];

      $element['actions'] = [
        '#type' => 'actions',
        'remove_button' => [
          '#delta' => $delta,
          '#name' => implode('_', $parents) . '_remove_button',
          '#type' => 'submit',
          '#value' => t('Remove'),
          '#validate' => [],
          '#submit' => [[static::class, 'removeSubmit']],
          '#limit_validation_errors' => [],
          '#ajax' => [
            'callback' => [$this, 'ajaxRemove'],
            'options' => $options,
            'effect' => 'fade',
            'wrapper' => $form['#wrapper_id'],
          ],
          '#weight' => 1000,
        ],
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $violation, array $form, FormStateInterface $form_state)  {
    return $element[$violation->arrayPropertyPath[0]];
  }

}
