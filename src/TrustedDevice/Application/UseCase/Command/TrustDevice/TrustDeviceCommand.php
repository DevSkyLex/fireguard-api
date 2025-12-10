<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Command\TrustDevice;

use Shared\Application\Message\CommandMessage;

/**
 * Command TrustDeviceCommand
 * @final
 *
 * Command to register a trusted device.
 *
 * @category Command
 * @package TrustedDevice\Application\UseCase\Command\TrustDevice
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TrustDeviceCommand implements CommandMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the command with the 
   * user ID, user agent, IP address, 
   * accept language, and TTL days.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user ID.
   * @param string $userAgent The user agent.
   * @param ?string $ipAddress The IP address.
   * @param ?string $acceptLanguage The accept language.
   * @param int $ttlDays The TTL days.
   */
  public function __construct(
    public readonly string $userId,
    public readonly string $userAgent,
    public readonly ?string $ipAddress = null,
    public readonly ?string $acceptLanguage = null,
    public readonly int $ttlDays = 30,
  ) {}
  //#endregion
}
