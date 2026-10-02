<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Query\Session\GetSession;

use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Domain\Exception\SessionNotFoundException;
use Session\Domain\ValueObject\SessionId;
use Shared\Application\Port\Outbound\CurrentActorPort;

/**
 * Handler GetSessionHandler.
 *
 * @category Handler
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
   * @param CurrentActorPort $actor account identity required to read a session
   */
  public function __construct(
    private SessionRepositoryPort $sessionRepository,
    private CurrentActorPort $actor,
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

    if (null === $session || $session->userId() !== $this->actor->userId()) {
      throw SessionNotFoundException::withId(id: $query->sessionId);
    }

    return new GetSessionResult(
      sessionId: (string) $session->id(),
      userId: $session->userId(),
      ipAddress: (string) $session->ipAddress(),
      userAgent: (string) $session->userAgent(),
      deviceType: $session->metadata()->deviceType,
      browser: $session->metadata()->browser,
      operatingSystem: $session->metadata()->operatingSystem,
      country: $session->metadata()->country,
      city: $session->metadata()->city,
      rememberMe: $session->metadata()->rememberMe,
      createdAt: $session->createdAt(),
      lastActivityAt: $session->lastActivityAt(),
      isRevoked: $session->isRevoked(),
    );
  }
  // #endregion
}
