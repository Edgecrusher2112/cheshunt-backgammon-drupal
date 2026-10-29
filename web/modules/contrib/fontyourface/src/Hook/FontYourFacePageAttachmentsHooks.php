<?php

namespace Drupal\fontyourface\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\fontyourface\Entity\Font;
use Drupal\fontyourface\Entity\FontDisplay;

/**
 * Hook implementations for fontyourface.
 */
class FontYourFacePageAttachmentsHooks {

  /**
   * Implements hook_page_attachments().
   *
   * D12 (change record 3496788): moved from a bare procedural function to
   * this OOP method so the order: Order::Last parameter actually takes
   * effect. Core's HookCollectorPass only reads a #[Hook] attribute's order
   * parameter when it is on a real class method (OOP scan branch); the
   * procedural-file scan branch never does (see
   * HookCollectorPass::collectModuleHookImplementations(),
   * core/lib/Drupal/Core/Hook/HookCollectorPass.php:397-405 vs. 424-466).
   * The submodule-reordering half of the old
   * fontyourface_module_implements_alter() (moving fontyourface_api-
   * implementing submodules to run directly after this one) is still NOT
   * reproduced -- #[ReorderHook] needs the target's class+method, and those
   * submodules' own hook_page_attachments() implementations are still
   * procedural, so no class/method target exists to point at.
   */
  #[Hook('page_attachments', order: Order::Last)]
  public function pageAttachments(&$page) {
    $config = \Drupal::config('fontyourface.settings');
    $fonts = &drupal_static('fontyourface_fonts', []);
    // Load all enabled fonts for theme if setting allows it.
    if ($config->get('load_all_enabled_fonts')) {
      if (empty($config->get('load_on_themes')) || in_array(\Drupal::theme()->getActiveTheme()->getName(), $config->get('load_on_themes'))) {
        foreach (Font::loadActivatedFonts() as $font) {
          $fonts[$font->url->value] = $font;
        }
      }
    }

    $font_preview = &drupal_static('fontyourface_font_preview');
    // On a font preview page, ONLY show the preview font and other base theme
    // font.
    if (empty($font_preview)) {
      // We are dealing with a regular page.
      $theme = \Drupal::theme()->getActiveTheme()->getName();
      $styles = FontDisplay::loadByTheme($theme);
      if (!empty($styles)) {
        $page['#attached']['library'][] = 'fontyourface/font_display_' . $theme;
        foreach ($styles as $style) {
          $font = $style->getFont();
          if (!empty($font)) {
            $fonts[$font->url->value] = $font;
          }
        }
      }
    }
  }

}
