<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Command\FeedToken\RotateCalendarFeedToken;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase RotateCalendarFeedTokenResult.
 *
 * Carries the raw secret exactly once, from the handler to the HTTP
 * response. It is never persisted, never logged, and never appears in a
 * domain event — after this response the backend only knows its hash.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RotateCalendarFeedTokenResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $secret the raw URL-safe secret, shown to the member this one time
   * @param DateTimeImmutable $createdAt the token creation timestamp
   * @param bool $rotated whether a previously active token was revoked by this call
   */
  public function __construct(
    public string $secret,
    public DateTimeImmutable $createdAt,
    public bool $rotated,
  ) {
  }
  // #endregion
}
