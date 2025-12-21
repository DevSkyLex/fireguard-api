<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Command\TrustDevice;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * Result TrustDeviceResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TrustDeviceResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize the result with the
   * device ID, token, device name, and
   * expiration date.
   *
   * @since 1.0.0
   *
   * @param string $deviceId the device ID
   * @param string $token the token
   * @param string $deviceName the device name
   * @param DateTimeImmutable $expiresAt the expiration date
   */
  public function __construct(
    public readonly string $deviceId,
    public readonly string $token,
    public readonly string $deviceName,
    public readonly DateTimeImmutable $expiresAt,
  ) {
  }
  // #endregion
}
