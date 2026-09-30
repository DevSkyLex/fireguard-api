<?php

declare(strict_types=1);

namespace TrustedDevice\Domain\ValueObject;

use DateTimeImmutable;

/** Persisted lifecycle timestamps of a trusted device. */
final readonly class TrustedDeviceTimeline
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries persisted use, expiry and creation timestamps for a trusted device.
   *
   * @access public
   *
   * @param DateTimeImmutable $lastUsedAt most recent use timestamp
   * @param DateTimeImmutable $expiresAt trust expiry timestamp
   * @param DateTimeImmutable $createdAt trust creation timestamp
   *
   * @return void
   */
  public function __construct(
    public DateTimeImmutable $lastUsedAt,
    public DateTimeImmutable $expiresAt,
    public DateTimeImmutable $createdAt,
  ) {
  }
  // #endregion
}
