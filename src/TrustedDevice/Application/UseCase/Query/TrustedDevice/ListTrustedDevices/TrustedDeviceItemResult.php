<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Query\TrustedDevice\ListTrustedDevices;

use DateTimeImmutable;

/**
 * Item TrustedDeviceItemResult.
 */
final readonly class TrustedDeviceItemResult
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Projects the trusted-device details shown in a user’s device list.
   *
   * @access public
   *
   * @param string $id trusted-device identifier
   * @param string $name user-facing device name
   * @param DateTimeImmutable $lastUsedAt most recent use timestamp
   * @param DateTimeImmutable $expiresAt time when the trusted-device grant expires
   * @param DateTimeImmutable $createdAt device trust creation timestamp
   * @param bool $isCurrentDevice whether this is the session’s current device
   *
   * @return void
   */
  public function __construct(
    public string $id,
    public string $name,
    public DateTimeImmutable $lastUsedAt,
    public DateTimeImmutable $expiresAt,
    public DateTimeImmutable $createdAt,
    public bool $isCurrentDevice = false,
  ) {
  }
  // #endregion
}
