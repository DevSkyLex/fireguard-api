<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\RefreshToken;

use Shared\Application\Message\QueryMessage;

/**
 * Query RefreshTokenQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RefreshTokenQuery implements QueryMessage
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the
     * RefreshTokenQuery class.
     *
     * @since 1.0.0
     *
     * @param string $refreshToken the encrypted refresh token
     */
    public function __construct(
        public string $refreshToken,
        public ?string $ipAddress = null,
    ) {
    }
    // #endregion
}
