<?php

declare(strict_types=1);

namespace Shared\Application\Query;

use Shared\Application\Message\ResultMessage;

/**
 * Result PaginatedResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @template T
 */
final readonly class PaginatedResult implements ResultMessage
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the PaginatedResult class.
     *
     * @since 1.0.0
     *
     * @param array<T> $items  the items in the current page
     * @param int      $total  the total number of items
     * @param int      $limit  the limit per page
     * @param int      $offset the current offset
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $limit,
        public readonly int $offset,
    ) {
    }
    // #endregion
}
