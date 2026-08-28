<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Query\FeedToken\GetCalendarFeedTokenMetadata;

use Calendar\Application\Port\Outbound\FeedToken\CalendarFeedTokenRepositoryPort;
use Calendar\Domain\Exception\CalendarFeedTokenNotFoundException;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetCalendarFeedTokenMetadataHandler.
 *
 * Scoped to the acting user's own token by construction — the lookup key is
 * the acting user id, so no cross-member disclosure is possible.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCalendarFeedTokenMetadataHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CalendarFeedTokenRepositoryPort $repository the feed token repository port
   */
  public function __construct(
    private CalendarFeedTokenRepositoryPort $repository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GetCalendarFeedTokenMetadataQuery $query the query payload
   *
   * @throws CalendarFeedTokenNotFoundException when the member has no active token
   *
   * @return GetCalendarFeedTokenMetadataResult the query result
   */
  public function __invoke(GetCalendarFeedTokenMetadataQuery $query): GetCalendarFeedTokenMetadataResult
  {
    $token = $this->repository->findActiveByOrganizationAndUser($query->organizationId, $query->actorUserId);
    if (null === $token) {
      throw new CalendarFeedTokenNotFoundException();
    }

    return new GetCalendarFeedTokenMetadataResult(
      createdAt: $token->createdAt(),
      lastUsedAt: $token->lastUsedAt(),
    );
  }
  // #endregion
}
