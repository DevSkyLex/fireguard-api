<?php

declare(strict_types=1);

namespace TrustedDevice\Application\UseCase\Command\TrustDevice;

use Shared\Application\Message\CommandMessage;

/**
 * Command TrustDeviceCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TrustDeviceCommand implements CommandMessage
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initialize the command with the
     * user ID, user agent, IP address,
     * accept language, and TTL days.
     *
     * @since 1.0.0
     *
     * @param string  $userId         the user ID
     * @param string  $userAgent      the user agent
     * @param ?string $ipAddress      the IP address
     * @param ?string $acceptLanguage the accept language
     * @param int     $ttlDays        the TTL days
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $userAgent,
        public readonly ?string $ipAddress = null,
        public readonly ?string $acceptLanguage = null,
        public readonly int $ttlDays = 30,
    ) {
    }
    // #endregion
}
