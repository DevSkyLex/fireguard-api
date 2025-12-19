<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Query\GetSession;

use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Domain\Exception\SessionNotFoundException;
use Session\Domain\ValueObject\SessionId;

/**
 * Handler GetSessionHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetSessionHandler implements \Shared\Application\Message\QueryHandler
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
     * Handles the GetSessionQuery.
     *
     * @since 1.0.0
     *
     * @param GetSessionQuery $query the query to handle
     *
     * @return GetSessionResult the result
     *
     * @throws SessionNotFoundException if session is not found
     */
    public function __invoke(GetSessionQuery $query): GetSessionResult
    {
        $sessionId = new SessionId(value: $query->sessionId);
        $session = $this->sessionRepository->findById(id: $sessionId);

        if (null === $session) {
            throw SessionNotFoundException::withId(id: $query->sessionId);
        }

        return new GetSessionResult(
            sessionId: (string) $session->id(),
            userId: $session->userId(),
            ipAddress: (string) $session->ipAddress(),
            userAgent: (string) $session->userAgent(),
            createdAt: $session->createdAt(),
            lastActivityAt: $session->lastActivityAt(),
            isRevoked: $session->isRevoked(),
        );
    }
    // #endregion
}
