<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\Pagination;

use function is_array;
use function is_numeric;
use function max;
use function min;

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

  /**
   * Hard ceiling on how many rows one request may ask for.
   *
   * `itemsPerPage` reaches the SQL `LIMIT` straight from the query string, and it
   * was only floored, never capped — so `?itemsPerPage=1000000` asked the database
   * to materialise a million rows, which is a denial of service anyone
   * authenticated can trigger by editing a URL.
   *
   * Set well above real usage rather than at it: the heaviest legitimate callers
   * (option pickers for checklist items, equipment and parent facilities) request
   * 200, so a tighter cap would silently truncate them and quietly corrupt whatever
   * is derived from the result. 500 leaves headroom while still bounding the blast
   * radius.
   */
  private const int MAX_ITEMS_PER_PAGE = 500;

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
    $itemsPerPage = min(
      self::MAX_ITEMS_PER_PAGE,
      max(1, is_numeric($itemsPerPageValue) ? (int) $itemsPerPageValue : $defaultItemsPerPage),
    );

    return new PaginationParams(
      page: $page,
      itemsPerPage: $itemsPerPage,
      offset: ($page - 1) * $itemsPerPage,
    );
  }
  // #endregion
}
