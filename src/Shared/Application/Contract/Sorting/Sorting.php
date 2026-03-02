<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Sorting;

/**
 * ValueObject Sorting.
 *
 * @category Sorting
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class Sorting
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the Sorting class.
   *
   * @since 1.0.0
   *
   * @param string $field the field to sort by
   * @param SortDirection $direction the sort direction (default: ASC)
   */
  public function __construct(
    public string $field,
    public SortDirection $direction = SortDirection::ASC,
  ) {
  }
  // #endregion
}
