<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Query\Session\ListUserSessions;

use Session\Application\Port\Outbound\SessionRepositoryPort;
use Session\Application\UseCase\Query\Session\GetSession\GetSessionResult;
use Session\Domain\Model\Session\Session;
use Shared\Application\Contract\Pagination\PaginatedResult;

use function array_map;
use function count;

/**
 * Handler ListUserSessionsHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListUserSessionsHandler implements \Shared\Application\Message\QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListUserSessionsHandler class.
   *
   * @since 1.0.0
   *
   * @param SessionRepositoryPort $sessionRepository the session repository
   */
  public function __construct(
    private readonly SessionRepositoryPort $sessionRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the ListUserSessionsQuery.
   *
   * @since 1.0.0
   *
   * @param ListUserSessionsQuery $query the query to handle
   *
   * @return PaginatedResult<GetSessionResult> the result
   */
  public function __invoke(ListUserSessionsQuery $query): PaginatedResult
  {
    $sessions = $query->activeOnly
      ? $this->sessionRepository->findActiveByUserId(userId: $query->userId)
      : $this->sessionRepository->findByUserId(userId: $query->userId);

    $results = array_map(
      callback: fn (Session $session): GetSessionResult => new GetSessionResult(
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
      ),
      array: $sessions,
    );

    $total = count($results);

    return new PaginatedResult(
      items: $results,
      total: $total,
      limit: $total,
      offset: 0,
    );
  }
  // #endregion
}
