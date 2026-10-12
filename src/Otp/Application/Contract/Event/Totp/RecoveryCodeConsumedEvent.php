<?php

declare(strict_types=1);

namespace Otp\Application\Contract\Event\Totp;

use DateTimeImmutable;

/**
 * Class RecoveryCodeConsumedEvent
 *
 * Publishes one durable recovery use fact with its purpose, never its proof or digest.
 *
 * @category Event
 */
final readonly class RecoveryCodeConsumedEvent
{
  // #region Properties
  /**
   * Property occurredAt
   *
   * Original consumption time.
   */
  public DateTimeImmutable $occurredAt;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $userId enrollment owner
   * @param string $purpose login or totp_disable
   * @param ?DateTimeImmutable $occurredAt original time when replayed from durable storage
   *
   * @return void
   */
  public function __construct(public string $userId, public string $purpose, ?DateTimeImmutable $occurredAt = null)
  {
    $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
  }
  // #endregion
}
