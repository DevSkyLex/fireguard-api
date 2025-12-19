<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\GenerateOtp;

use Otp\Domain\ValueObject\{
    OtpChannel,
    OtpPurpose,
};
use Shared\Application\Message\CommandMessage;

/**
 * Command GenerateOtpCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GenerateOtpCommand implements CommandMessage
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the
     * GenerateOtpCommand class.
     *
     * @since 1.0.0
     *
     * @param string     $userId      the user ID
     * @param OtpPurpose $purpose     the OTP purpose
     * @param OtpChannel $channel     the delivery channel
     * @param string     $recipient   the recipient (email/phone)
     * @param int|null   $ttlSeconds  custom TTL in seconds
     * @param int|null   $maxAttempts custom max attempts
     */
    public function __construct(
        public readonly string $userId,
        public readonly OtpPurpose $purpose,
        public readonly OtpChannel $channel,
        public readonly string $recipient,
        public readonly ?int $ttlSeconds = null,
        public readonly ?int $maxAttempts = null,
    ) {
    }
    // #endregion
}
