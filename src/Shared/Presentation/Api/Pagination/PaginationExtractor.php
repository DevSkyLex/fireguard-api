<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\Pagination;

use function is_array;
use function is_numeric;
use function max;

/**
 * Service PaginationExtractor.
 *
 * Extracts and normalizes pagination parameters from an API Platform provider
 * context (`?page=…&itemsPerPage=…`), mirroring {@link SearchExtractor} and
 * {@link SortingExtractor} so hand-rolled providers stop re-parsing the same
 * page/offset block.
 *
 * @category Pagination
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PaginationExtractor
{
  private const int DEFAULT_ITEMS_PER_PAGE = 30;

  // #region Methods
  /**
   * Method fromContext.
   *
   * Reads `page` / `itemsPerPage` from the context filters, coercing invalid or
   * missing values to safe defaults (both clamped to a minimum of 1) and
   * deriving the row offset.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $context the provider context
   * @param int $defaultItemsPerPage the page size used when none is requested
   *
   * @return PaginationParams the normalized pagination triple
   */
  public static function fromContext(
    array $context,
    int $defaultItemsPerPage = self::DEFAULT_ITEMS_PER_PAGE,
  ): PaginationParams {
    $filters = $context['filters'] ?? [];
    if (!is_array($filters)) {
      $filters = [];
    }

    $pageValue = $filters['page'] ?? 1;
    $itemsPerPageValue = $filters['itemsPerPage'] ?? $defaultItemsPerPage;

    $page = max(1, is_numeric($pageValue) ? (int) $pageValue : 1);
    $itemsPerPage = max(1, is_numeric($itemsPerPageValue) ? (int) $itemsPerPageValue : $defaultItemsPerPage);

    return new PaginationParams(
      page: $page,
      itemsPerPage: $itemsPerPage,
      offset: ($page - 1) * $itemsPerPage,
    );
  }
  // #endregion
}
