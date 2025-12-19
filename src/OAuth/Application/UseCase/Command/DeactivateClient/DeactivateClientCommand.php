<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\DeactivateClient;

use Shared\Application\Message\CommandMessage;

/**
 * Command DeactivateClientCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeactivateClientCommand implements CommandMessage
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the
     * DeactivateClientCommand class.
     *
     * @since 1.0.0
     *
     * @param string $clientId the client ID
     */
    public function __construct(
        public readonly string $clientId,
    ) {
    }
    // #endregion
}
