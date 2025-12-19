<?php

declare(strict_types=1);

namespace Shared\Application\Query;

/**
 * ValueObject Pagination.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class Pagination
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the Pagination class.
     *
     * @since 1.0.0
     *
     * @param int $offset the offset (default: 0)
     * @param int $limit  the limit (default: 20)
     */
    public function __construct(
        public int $offset = 0,
        public int $limit = 20,
    ) {
    }
    // #endregion
}
