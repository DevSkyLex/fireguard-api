<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Http;

use function array_pop;
use function is_array;
use function preg_match_all;

/**
 * Class ParsedQueryParameters
 *
 * Combines parsed values with legacy filters without losing undeclared keys.
 * Bracketed names keep the nested shape used by existing HTTP endpoints.
 *
 * @category HTTP Contract
 */
final readonly class ParsedQueryParameters
{
  // #region Methods
  /**
   * Method merge
   *
   * Parsed values override the corresponding leaf, including null and false.
   * Both inputs remain unchanged; a scalar parent is replaced only when needed.
   *
   * @access public
   *
   * @param array<string, mixed> $parsedValues present parsed query values
   * @param array<array-key, mixed> $legacyFilters undeclared values and provider fallbacks
   *
   * @return array<string, mixed> merged query values with bracketed nesting
   */
  public static function merge(array $parsedValues, array $legacyFilters): array
  {
    $values = $legacyFilters;
    foreach ($parsedValues as $key => $value) {
      preg_match_all('/[^\[\]]+/', $key, $matches);
      $segments = $matches[0];
      $leaf = array_pop($segments);
      if (null === $leaf) {
        continue;
      }

      $target = &$values;
      foreach ($segments as $segment) {
        if (!is_array($target[$segment] ?? null)) {
          $target[$segment] = [];
        }
        $target = &$target[$segment];
      }
      $target[$leaf] = $value;
      unset($target);
    }

    /** @var array<string, mixed> $values HTTP query parameter names */
    return $values;
  }
  // #endregion
}
