<?php

namespace Drupal\block_attributes;

/**
 * Class BlockAttributesChecker.
 *
 * Provides methods to check block attributes.
 */
class BlockAttributesChecker {

  /**
   * Pattern a whole attribute name has to match to be considered safe.
   *
   * Covers every attribute the module is meant to provide, including class,
   * id, role, data-* and aria-*.
   */
  const ATTRIBUTE_NAME_PATTERN = '/^[a-zA-Z][a-zA-Z0-9_-]*$/';

  /**
   * Checks if a given attribute is allowed.
   *
   * @param string $attribute
   *   The attribute to check.
   *
   * @return bool
   *   TRUE if the attribute is allowed, FALSE otherwise.
   */
  public static function isAttributeAllowed($attribute) {
    if (!is_string($attribute)) {
      return FALSE;
    }

    // Validate the name as a whole instead of looking for bad substrings.
    if (!preg_match(self::ATTRIBUTE_NAME_PATTERN, $attribute)) {
      return FALSE;
    }

    // Match JS event handlers.
    $result = (bool) preg_match('/^on[a-z]+$/', strtolower($attribute));
    // If the attribute matches a JS event handler, return FALSE.
    return !$result;
  }

  /**
   * Filters the attribute values stored on a block before rendering.
   *
   * The values stored on a block can reach the site through configuration
   * import without ever passing the block form, so they are checked again
   * against the attributes defined in config.
   *
   * @param mixed $values
   *   The attribute values stored on the block, keyed by attribute name.
   * @param mixed $definitions
   *   The attribute definitions from config, keyed by attribute name.
   *
   * @return string[]
   *   The values that are safe to render, keyed by attribute name.
   */
  public static function filterAttributeValues($values, $definitions) {
    if (!is_array($values) || !is_array($definitions)) {
      return [];
    }

    $filtered = [];
    foreach ($values as $name => $value) {
      if (!static::isAttributeAllowed($name) || !array_key_exists($name, $definitions)) {
        continue;
      }
      if (!is_scalar($value)) {
        continue;
      }
      $value = (string) $value;

      $options = $definitions[$name]['options'] ?? NULL;
      if (!empty($options) && is_array($options) && !in_array($value, array_map('strval', array_keys($options)), TRUE)) {
        continue;
      }

      $filtered[$name] = $value;
    }

    return $filtered;
  }

}
