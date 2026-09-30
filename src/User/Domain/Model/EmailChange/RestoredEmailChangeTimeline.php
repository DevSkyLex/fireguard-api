<?php

declare(strict_types=1);

namespace User\Domain\Model\EmailChange;

use DateTimeImmutable;

/** Persisted lifecycle timestamps of an email change request. */
final readonly class RestoredEmailChangeTimeline
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries request, expiry and optional confirmation times for an email change.
   *
   * @access public
   *
   * @param DateTimeImmutable $requestedAt time when the address change was requested
   * @param DateTimeImmutable $expiresAt time after which the confirmation request expires
   * @param ?DateTimeImmutable $confirmedAt optional time when the change was confirmed
   *
   * @return void
   */
  public function __construct(
    public DateTimeImmutable $requestedAt,
    public DateTimeImmutable $expiresAt,
    public ?DateTimeImmutable $confirmedAt,
  ) {
  }
  // #endregion
}
