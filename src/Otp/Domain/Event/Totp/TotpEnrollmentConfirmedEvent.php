<?php

declare(strict_types=1);

namespace Otp\Domain\Event\Totp;

use DateTimeImmutable;

/**
 * Event TotpEnrollmentConfirmedEvent.
 *
 * Raised when a user confirms a pending TOTP secret, activating TOTP MFA.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TotpEnrollmentConfirmedEvent
{
  // #region Properties
  /**
   * Property occurredAt.
   */
  public DateTimeImmutable $occurredAt;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   */
  public function __construct(
    public string $userId,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
