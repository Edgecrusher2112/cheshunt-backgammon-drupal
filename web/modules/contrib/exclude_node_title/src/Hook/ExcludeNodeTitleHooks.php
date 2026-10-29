<?php

namespace Drupal\exclude_node_title\Hook;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\exclude_node_title\ExcludeNodeTitleManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Hook implementations for exclude_node_title.
 */
class ExcludeNodeTitleHooks {

  use StringTranslationTrait;

  public function __construct(
    protected ExcludeNodeTitleManagerInterface $excludeManager,
    protected RouteMatchInterface $routeMatch,
    protected AccountProxyInterface $currentUser,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    if ($route_name == 'help.page.exclude_node_title') {
      $output = '<h3>' . $this->t('About') . '</h3>';
      $output .= '<p>' . $this->t('Provides the option of excluding node title(s) from display by individual node or by bundle and view mode.') . '</p>';
      $output .= '<h3>' . $this->t('Uses') . '</h3>';
      $output .= '<dl>';
      $output .= '<dt>' . $this->t('Exclude title(s) by bundle and view mode') . '</dt>';
      $output .= '<dd>' . $this->t('<em>All nodes.</em> excludes the Node title from all the node displays using the View Mode(s) you select.') . '</dd>';
      $output .= '<dt>' . $this->t('Exclude title(s) on a node-by-node basis') . '</dt>';
      $output .= '<dd>' . $this->t('<em>User defined nodes.</em> does not, by default, hide any Node title. However, it provides users with the permission to exclude node title a checkbox on the node edit form that allows them to exclude node titles, from the View Modes selected in this form, on a node-by-node basis.') . '</dd>';
      $output .= '<dt>' . $this->t('Exclude title(s) by role') . '</dt>';
      $output .= '<dd>' . $this->t('You may exclude title(s) by role using the <a href=":permission">Use exclude node title</a> permission.', [
        ':permission' => 'https://contribd8.dev/admin/people/permissions#module-exclude_node_title',
      ]) . '</dd>';
      $output .= '</dl>';
      return $output;
    }
  }

  /**
   * Implements hook_preprocess_html().
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(&$vars): void {
    $route_name = $this->routeMatch->getRouteName();
    switch ($route_name) {
      case 'entity.node.edit_form':
        $node = $this->routeMatch->getParameter('node');
        $this->excludeManager->preprocessTitle($vars, $node, 'nodeform');
        break;

      case 'entity.node.canonical':
        $node = $this->routeMatch->getParameter('node');
        if ($this->excludeManager->isTitleExcluded($node)) {
          unset($vars['head_title']['title']);
          // Add a class to pages with excluded node title.
          $vars['attributes']['class'][] = 'exclude-node-title';
        }
        break;
    }
  }

  /**
   * Implements hook_preprocess_page_title().
   */
  #[Hook('preprocess_page_title')]
  public function preprocessPageTitle(&$vars): void {
    $this->reusablePreprocess($vars);
  }

  /**
   * Implements hook_preprocess_page().
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(&$vars): void {
    $this->reusablePreprocess($vars);
  }

  /**
   * Implements hook_preprocess_node().
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(&$vars): void {
    $this->excludeManager->preprocessTitle($vars, $vars['node'], $vars['view_mode']);
  }

  /**
   * Implements hook_preprocess_field().
   */
  #[Hook('preprocess_field')]
  public function preprocessField(&$vars): void {
    if ($this->excludeManager->isSearchExcluded() && $vars['element']['#field_name'] == 'title' && $vars['element']['#object'] instanceof NodeInterface && $this->excludeManager->isTitleExcluded($vars['element']['#object'], 'search_result') && $vars['element']['#view_mode'] == 'search_result') {
      foreach ($vars['items'] as &$item) {
        $item['content']['#context']['value'] = '';
      }
    }
  }

  /**
   * Implements hook_preprocess_search_result().
   */
  #[Hook('preprocess_search_result')]
  public function preprocessSearchResult(&$vars): void {
    if ($vars['plugin_id'] == 'node_search' && $this->excludeManager->isSearchExcluded() && $this->excludeManager->isTitleExcluded($vars['result']['node']->id(), 'search_result')) {
      $vars['result']['title'] = '';
      $vars['title'] = '';
    }
  }

  /**
   * Implements hook_node_update().
   */
  #[Hook('node_update')]
  public function nodeUpdate(NodeInterface $node): void {
    if ($this->checkPerm($node)) {
      $this->setFlag($node, $node->exclude_node_title);
    }
  }

  /**
   * Implements hook_node_insert().
   */
  #[Hook('node_insert')]
  public function nodeInsert(NodeInterface $node): void {
    if ($this->checkPerm($node)) {
      $this->setFlag($node, $node->exclude_node_title);
    }
  }

  /**
   * Implements hook_node_delete().
   */
  #[Hook('node_delete')]
  public function nodeDelete(NodeInterface $node): void {
    if ($node->exclude_node_title == 1) {
      $this->setFlag($node, 0);
    }
  }

  /**
   * Implements hook_form_node_form_alter().
   */
  #[Hook('form_node_form_alter')]
  public function formNodeFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    $form_object = $form_state->getFormObject();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $form_object->getEntity();
    $node_type = $node->getType();
    if ($this->excludeManager->isTitleExcluded($node->id(), 'nodeform')) {
      $form['title']['#access'] = FALSE;
    }
    // Make sure user have permissions correct.
    if (!$this->checkPerm($node)) {
      return FALSE;
    }
    // Don't bother to add form element if the content type isn't configured
    // to be excluded by user...
    if ($this->excludeManager->getBundleExcludeMode($node_type) == 'user') {
      $weight = $form['title']['#weight'] + 0.1;
      $form['exclude_node_title'] = [
        '#type' => 'checkbox',
        '#field_name' => 'exclude_node_title',
        '#title' => $this->t('Exclude title from display'),
        '#required' => FALSE,
        '#element_validate' => [
          [self::class, 'setFormValue'],
        ],
        '#default_value' => $this->excludeManager->isNodeExcluded($node),
        '#weight' => $weight,
      ];
    }
  }

  /**
   * Implements hook_field_attach_delete_bundle().
   */
  #[Hook('field_attach_delete_bundle')]
  public function fieldAttachDeleteBundle($entity_type, $bundle, $instances): void {
    $config = $this->configFactory->getEditable('exclude_node_title.settings');
    // When deleting a content type, we make sure and clean our variable.
    if ($entity_type == 'node') {
      $config->clear('content_types.' . $bundle)
        ->clear('content_type_modes.' . $bundle)
        ->save();
    }
  }

  /**
   * Implements hook_ds_fields_info_alter().
   */
  #[Hook('ds_fields_info_alter')]
  public function dsFieldsInfoAlter(&$plugins): void {
    if (!empty($plugins['node_title'])) {
      $plugins['node_title']['class'] = 'Drupal\exclude_node_title\Plugin\DsField\Node\NodeTitle';
      $plugins['node_title']['provider'] = 'exclude_node_title';
    }
  }

  /**
   * Implements hook_block_view_BASE_BLOCK_ID_alter().
   */
  #[Hook('block_view_page_title_block_alter')]
  public function blockViewPageTitleBlockAlter(array &$build, BlockPluginInterface $block): void {
    // Get current node.
    $node = $this->routeMatch->getParameter('node');
    if (!$node) {
      return;
    }
    // Check if the title for this node has been excluded from display.
    if ($this->excludeManager->isNodeExcluded($node)) {
      $build['#access'] = FALSE;
    }
  }

  /**
   * Form element validate callback to set the exclude flag on the node.
   *
   * Public static so it can be referenced as a serializable `#element_validate`
   * callback from form arrays.
   */
  public static function setFormValue($element, FormStateInterface $form_state, $form): void {
    $values = $form_state->getValues();
    $node = $form_state->getFormObject()->getEntity();
    $node->exclude_node_title = $values['exclude_node_title'];
  }

  /**
   * Check permission to change node title exclusion for the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node that is being inserted or updated.
   *
   * @return bool
   *   Returns TRUE if the current user may exclude this node's title.
   */
  protected function checkPerm(NodeInterface $node): bool {
    if ($this->currentUser->hasPermission('exclude any node title')) {
      return TRUE;
    }
    if ($this->currentUser->hasPermission('exclude own node title') && $this->currentUser->id() == $node->getOwnerId()) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Set exclude_node_title flag for the given node.
   */
  protected function setFlag($node, $value = 1): void {
    if ($value === 1 && !$this->excludeManager->isNodeExcluded($node)) {
      $this->excludeManager->addNodeToList($node);
    }
    if ($value === 0 && $this->excludeManager->isNodeExcluded($node)) {
      $this->excludeManager->removeNodeFromList($node);
    }
  }

  /**
   * Re-usable preprocess code.
   */
  protected function reusablePreprocess(&$vars): void {
    $route_name = $this->routeMatch->getRouteName();
    switch ($route_name) {
      case 'entity.node.edit_form':
        $node = $this->routeMatch->getParameter('node');
        $this->excludeManager->preprocessTitle($vars, $node, 'nodeform');
        break;

      case 'entity.node.canonical':
        $node = $this->routeMatch->getParameter('node');
        $this->excludeManager->preprocessTitle($vars, $node, 'full');
        break;

      case 'entity.node.preview':
        $node = $this->routeMatch->getParameter('node_preview');
        if ($this->checkPerm($node)) {
          $this->setFlag($node, $node->exclude_node_title);
        }
        $this->excludeManager->preprocessTitle($vars, $node, 'full');
        break;
    }
  }

}
