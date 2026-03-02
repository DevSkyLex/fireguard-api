<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\Search;

use function array_filter;
use function array_values;
use function is_string;
use function mb_stripos;

/**
 * Service CollectionSearcher.
 *
 * Filters an array of output DTOs by matching a search term
 * against specified string fields (case-insensitive).
 *
 * @category Search
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CollectionSearcher
{
  // #region Methods
  /**
   * Method search.
   *
   * Filters items where at least one of the searchable fields
   * contains the given search term (case-insensitive).
   *
   * @since 1.0.0
   *
   * @template T of object
   *
   * @param array<T> $items the items to filter
   * @param string|null $search the search term (null = no filtering)
   * @param list<string> $searchableFields the field names to search in
   *
   * @return list<T> the filtered items
   */
  public static function search(array $items, ?string $search, array $searchableFields): array
  {
    if (null === $search || [] === $items || [] === $searchableFields) {
      return array_values($items);
    }

    return array_values(array_filter($items, static function (object $item) use ($search, $searchableFields): bool {
      foreach ($searchableFields as $field) {
        $value = $item->{$field} ?? null;

        if (!is_string($value)) {
          continue;
        }

        if (false !== mb_stripos($value, $search)) {
          return true;
        }
      }

      return false;
    }));
  }
  // #endregion
}
