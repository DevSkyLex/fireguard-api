<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Command\FeedToken\RevokeCalendarFeedToken;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase RevokeCalendarFeedTokenResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeCalendarFeedTokenResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $tokenId the revoked token identifier
   * @param DateTimeImmutable $revokedAt the revocation timestamp
   */
  public function __construct(
    public string $tokenId,
    public DateTimeImmutable $revokedAt,
  ) {
  }
  // #endregion
}
