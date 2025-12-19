<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\RevokeSession;

use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Domain\Exception\SessionNotFoundException;
use Session\Domain\ValueObject\SessionId;

/**
 * Handler RevokeSessionHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeSessionHandler implements \Shared\Application\Message\CommandHandler
{
    // #region Constructor
    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param SessionRepositoryPort $sessionRepository the session repository
     */
    public function __construct(
        private SessionRepositoryPort $sessionRepository,
    ) {
    }
    // #endregion

    // #region Methods
    /**
     * Method __invoke.
     *
     * Handles the RevokeSessionCommand.
     *
     * @since 1.0.0
     *
     * @param RevokeSessionCommand $command the command to handle
     *
     * @throws SessionNotFoundException if session is not found
     */
    public function __invoke(RevokeSessionCommand $command): void
    {
        $sessionId = new SessionId(value: $command->sessionId);
        $session = $this->sessionRepository->findById(id: $sessionId);

        if (null === $session) {
            throw SessionNotFoundException::withId(id: $command->sessionId);
        }

        $session->revoke();

        $this->sessionRepository->save(session: $session);
    }
    // #endregion
}
