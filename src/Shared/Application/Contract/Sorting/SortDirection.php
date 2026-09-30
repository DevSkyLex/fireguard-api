<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Sorting;

/**
 * Enum SortDirection.
 *
 * @category Sorting
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum SortDirection: string
{
  /**
   * Case ASC
   */
  case ASC = 'asc';

  /**
   * Case DESC
   */
  case DESC = 'desc';
}
