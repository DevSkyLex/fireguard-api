<?php

declare(strict_types=1);

namespace Otp\Domain\Event\Totp;

use DateTimeImmutable;

/**
 * Event TotpEnrollmentDisabledEvent.
 *
 * Raised when a user disables an active TOTP enrollment.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TotpEnrollmentDisabledEvent
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
