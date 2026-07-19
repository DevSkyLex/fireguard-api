<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Search;

use function trim;

/**
 * ValueObject SearchCriteria.
 *
 * Carries the free-text search term for a collection query in a single,
 * framework-free type instead of a bare `?string`. Normalization (trimming,
 * blank-to-null) is centralized here; the list of searchable columns stays
 * repository-owned since it is a persistence/mapping concern.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SearchCriteria
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the SearchCriteria class.
   *
   * @since 1.0.0
   *
   * @param ?string $term the raw search term (default: null)
   */
  public function __construct(
    public ?string $term = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method isEmpty.
   *
   * Reports whether the criteria carries no usable search term.
   *
   * @since 1.0.0
   *
   * @return bool whether the criteria is empty
   */
  public function isEmpty(): bool
  {
    return null === $this->normalizedTerm();
  }

  /**
   * Method normalizedTerm.
   *
   * Trims the raw term and collapses a blank value to null.
   *
   * @since 1.0.0
   *
   * @return ?string the normalized term
   */
  public function normalizedTerm(): ?string
  {
    if (null === $this->term) {
      return null;
    }

    $trimmed = trim($this->term);

    return '' !== $trimmed ? $trimmed : null;
  }
  // #endregion
}
