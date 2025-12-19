<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Query\CheckDeviceTrusted;

/**
 * Query CheckDeviceTrustedQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CheckDeviceTrustedQuery
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the
     * CheckDeviceTrustedQuery class.
     *
     * @since 1.0.0
     *
     * @param string $token  the token
     * @param string $userId the user ID
     */
    public function __construct(
        public readonly string $token,
        public readonly string $userId,
    ) {
    }
    // #endregion
}
