<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\RegisterClient;

use Shared\Application\Message\ResultMessage;

/**
 * Result RegisterClientResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RegisterClientResult implements ResultMessage
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the
     * RegisterClientResult class.
     *
     * @since 1.0.0
     *
     * @param string $clientId     the client ID (UUID)
     * @param string $clientSecret the plain client secret (shown only once)
     */
    public function __construct(
        public readonly string $clientId,
        public readonly string $clientSecret,
    ) {
    }
    // #endregion
}
