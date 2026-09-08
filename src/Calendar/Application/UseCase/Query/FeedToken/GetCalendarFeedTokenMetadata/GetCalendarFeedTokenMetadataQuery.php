<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Query\FeedToken\GetCalendarFeedTokenMetadata;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetCalendarFeedTokenMetadataQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCalendarFeedTokenMetadataQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $actorUserId the acting (and owning) user identifier
   */
  public function __construct(
    public string $organizationId,
    public string $actorUserId,
  ) {
  }
  // #endregion
}
