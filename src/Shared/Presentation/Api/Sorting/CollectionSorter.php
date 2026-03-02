<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\Sorting;

use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

use function usort;

/**
 * Service CollectionSorter.
 *
 * Sorts an array of output DTOs by a given field and direction.
 *
 * @category Sorting
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CollectionSorter
{
  // #region Methods
  /**
   * Method sort.
   *
   * Sorts items by the specified sorting field and direction.
   *
   * @since 1.0.0
   *
   * @template T of object
   *
   * @param list<T> $items the items to sort
   * @param Sorting $sorting the sorting configuration
   *
   * @return list<T> the sorted items
   */
  public static function sort(array $items, Sorting $sorting): array
  {
    if ([] === $items) {
      return $items;
    }

    $field = $sorting->field;

    usort($items, static function (object $a, object $b) use ($field, $sorting): int {
      $aValue = $a->{$field};
      $bValue = $b->{$field};

      $comparison = $aValue <=> $bValue;

      return SortDirection::DESC === $sorting->direction ? -$comparison : $comparison;
    });

    return $items;
  }
  // #endregion
}
