<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\ValidateToken;

use Shared\Application\Message\QueryMessage;

/**
 * Query ValidateTokenQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ValidateTokenQuery implements QueryMessage
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the
     * ValidateTokenQuery class.
     *
     * @since 1.0.0
     *
     * @param string $accessToken the access token to validate
     */
    public function __construct(
        public string $accessToken,
    ) {
    }
    // #endregion
}
