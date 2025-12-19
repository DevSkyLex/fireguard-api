<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Mfa;

use Auth\Application\Port\Outbound\Mfa\ChallengeGeneratorPort;
use Auth\Application\UseCase\Command\MfaChallenge\MfaChallengeCommand;
use Auth\Application\UseCase\Command\MfaChallenge\MfaChallengeResult;
use Otp\Application\UseCase\Command\GenerateOtp\GenerateOtpCommand;
use Otp\Application\UseCase\Command\GenerateOtp\GenerateOtpHandler;
use Otp\Domain\ValueObject\OtpChannel;
use Otp\Domain\ValueObject\OtpPurpose;

/**
 * Adapter OtpModuleChallengeGeneratorAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OtpModuleChallengeGeneratorAdapter implements ChallengeGeneratorPort
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the OtpModuleChallengeGeneratorAdapter class.
     *
     * @since 1.0.0
     *
     * @param GenerateOtpHandler $handler the OTP generation handler
     */
    public function __construct(
        private GenerateOtpHandler $handler,
    ) {
    }
    // #endregion

    // #region Methods
    /**
     * Method generate
     * {@inheritDoc}
     *
     * Generates a challenge using the OTP module.
     *
     * @since 1.0.0
     *
     * @param MfaChallengeCommand $command the MFA challenge command
     *
     * @return MfaChallengeResult the MFA challenge result
     */
    public function generate(MfaChallengeCommand $command): MfaChallengeResult
    {
        $otpCommand = new GenerateOtpCommand(
            userId: $command->userId,
            purpose: OtpPurpose::from(value: $command->purpose),
            channel: OtpChannel::from(value: $command->channel),
            recipient: $command->recipient,
            ttlSeconds: $command->ttlSeconds,
        );

        $result = $this->handler->__invoke(command: $otpCommand);

        return new MfaChallengeResult(
            challengeToken: $result->token,
            maskedRecipient: $result->maskedRecipient,
            expiresAt: $result->expiresAt,
            maxAttempts: $result->maxAttempts,
        );
    }
    // #endregion
}
