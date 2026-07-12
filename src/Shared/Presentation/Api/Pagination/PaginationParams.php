<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\Pagination;

/**
 * Value object PaginationParams.
 *
 * Normalized page / items-per-page / offset triple extracted from an API
 * Platform provider context by {@link PaginationExtractor}.
 *
 * @category Pagination
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PaginationParams
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param int $page the 1-based current page
   * @param int $itemsPerPage the page size
   * @param int $offset the zero-based row offset (`(page - 1) * itemsPerPage`)
   */
  public function __construct(
    public int $page,
    public int $itemsPerPage,
    public int $offset,
  ) {
  }
  // #endregion
}
