<?php

namespace Drupal\block_attributes\Form;

use Drupal\block_attributes\BlockAttributesChecker;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for the block attributes.
 *
 * @package Drupal\block_attributes\Form
 */
class ConfigForm extends ConfigFormBase {

  /**
   * Returns the module_handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a ConfigForm form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed config manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Interface for classes that manage a set of enabled modules.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typed_config_manager, ModuleHandlerInterface $module_handler) {
    parent::__construct($config_factory, $typed_config_manager);

    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'block_attributes.config',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'block_attributes_config';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('block_attributes.config');
    $conf = $config->get();
    $config_text = Yaml::encode($conf);

    if (!$this->moduleHandler->moduleExists('yaml_editor')) {
      $message = $this->t('It is recommended to install the <a href="@yaml-editor">YAML Editor</a> module for easier editing.', [
        '@yaml-editor' => 'https://www.drupal.org/project/yaml_editor',
      ]);

      $this->messenger()->addWarning($message, 'warning');
    }

    $form['config'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Configuration'),
      '#description' => $this->t('Available attributes can be defined in YAML syntax.'),
      '#default_value' => $config_text,
      '#rows' => 15,
      '#attributes' => ['data-yaml-editor' => 'true'],
    ];

    // Use module's YAML config file for example structure.
    $module_path = $this->moduleHandler->getModule('block_attributes')->getPath();
    $yml_text = file_get_contents($module_path . '/config/install/block_attributes.config.yml');

    $form['example'] = [
      '#type' => 'details',
      '#title' => $this->t('Example structure'),
    ];

    $form['example']['description'] = [
      '#prefix' => '<p>',
      '#suffix' => '</p>',
      '#markup' => $this->t('Each attribute has an optional label and description.'),
    ];

    $form['example']['code'] = [
      '#prefix' => '<pre>',
      '#suffix' => '</pre>',
      '#markup' => $yml_text,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $config_text = $form_state->getValue('config') ?: 'attributes:';
    $decodedText = Yaml::decode($config_text);
    $attributes = is_array($decodedText['attributes'] ?? NULL) ? $decodedText['attributes'] : [];
    foreach ($attributes as $attributeName => $info) {
      if (!BlockAttributesChecker::isAttributeAllowed($attributeName)) {
        $form_state->setErrorByName('config', $this->t('%name is not a valid attribute name. A name has to start with a letter and may only contain letters, digits, hyphens and underscores, and HTML event attributes are not allowed. Visit this <a href="https://www.w3schools.com/tags/ref_eventattributes.asp" rel="noopener noreferrer" target="_blank">page</a> for the whole list of unallowed attributes.', ['%name' => $attributeName]));
        continue;
      }

      // The block form reads the label, description and options of every
      // attribute, so each entry has to have the expected structure.
      if (!is_array($info)
        || !is_scalar($info['label'] ?? '')
        || !is_scalar($info['description'] ?? '')
        || !is_array($info['options'] ?? [])
        || count(array_filter($info['options'] ?? [], 'is_scalar')) !== count($info['options'] ?? [])) {
        $form_state->setErrorByName('config', $this->t('The definition of %name is not valid. The label and description have to be text, and the options have to be a list of values with text labels.', ['%name' => $attributeName]));
      }
    }

    try {
      $form_state->set('config', $decodedText);
    }
    catch (InvalidDataTypeException $e) {
      $form_state->setErrorByName('config', $e->getMessage());
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $form_state->get('config');

    $this->config('block_attributes.config')
      ->setData($config)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
