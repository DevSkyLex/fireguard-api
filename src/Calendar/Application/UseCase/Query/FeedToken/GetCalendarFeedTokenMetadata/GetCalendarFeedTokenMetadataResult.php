<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Query\FeedToken\GetCalendarFeedTokenMetadata;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetCalendarFeedTokenMetadataResult.
 *
 * Metadata only — deliberately carries neither the secret (gone after
 * creation) nor its hash.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCalendarFeedTokenMetadataResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $createdAt the token creation timestamp
   * @param ?DateTimeImmutable $lastUsedAt the last recorded feed fetch (hour-throttled), when any
   */
  public function __construct(
    public DateTimeImmutable $createdAt,
    public ?DateTimeImmutable $lastUsedAt,
  ) {
  }
  // #endregion
}
