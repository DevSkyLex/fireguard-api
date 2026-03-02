<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\Search;

use function is_array;
use function is_string;
use function trim;

/**
 * Service SearchExtractor.
 *
 * Extracts a search query string from API Platform context filters.
 * Expects the format: ?search=term.
 *
 * @category Search
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SearchExtractor
{
  // #region Methods
  /**
   * Method fromContext.
   *
   * Extracts the search term from the API Platform provider context.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $context the provider context
   */
  public static function fromContext(array $context): ?string
  {
    $filters = $context['filters'] ?? [];
    if (!is_array($filters)) {
      return null;
    }

    $search = $filters['search'] ?? null;
    if (!is_string($search)) {
      return null;
    }

    $trimmed = trim($search);

    return '' !== $trimmed ? $trimmed : null;
  }
  // #endregion
}
