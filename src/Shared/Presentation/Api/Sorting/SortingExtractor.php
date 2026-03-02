<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\Sorting;

use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

use function array_keys;
use function in_array;
use function is_array;
use function is_string;
use function strtolower;

/**
 * Service SortingExtractor.
 *
 * Extracts sorting parameters from API Platform context filters.
 * Expects the standard API Platform order format: ?order[field]=asc|desc.
 *
 * @category Sorting
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SortingExtractor
{
  // #region Methods
  /**
   * Method fromContext.
   *
   * Extracts sorting from the API Platform provider context.
   * Only the first valid order field is used.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $context the provider context
   * @param list<string> $allowedFields the allowed field names
   * @param string $defaultField the default field to sort by
   * @param SortDirection $defaultDirection the default sort direction
   */
  public static function fromContext(
    array $context,
    array $allowedFields,
    string $defaultField,
    SortDirection $defaultDirection = SortDirection::ASC,
  ): Sorting {
    $filters = $context['filters'] ?? [];
    if (!is_array($filters)) {
      return new Sorting($defaultField, $defaultDirection);
    }

    $order = $filters['order'] ?? [];
    if (!is_array($order)) {
      return new Sorting($defaultField, $defaultDirection);
    }

    foreach (array_keys($order) as $field) {
      if (!is_string($field) || !in_array($field, $allowedFields, true)) {
        continue;
      }

      $directionValue = $order[$field];
      if (!is_string($directionValue)) {
        continue;
      }

      $direction = SortDirection::tryFrom(strtolower($directionValue)) ?? $defaultDirection;

      return new Sorting($field, $direction);
    }

    return new Sorting($defaultField, $defaultDirection);
  }
  // #endregion
}
