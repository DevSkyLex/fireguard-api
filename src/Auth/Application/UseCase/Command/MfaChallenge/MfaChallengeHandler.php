<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\MfaChallenge;

use Otp\Application\UseCase\Command\GenerateOtp\GenerateOtpCommand;
use Otp\Application\UseCase\Command\GenerateOtp\GenerateOtpResult;
use Otp\Domain\ValueObject\OtpChannel;
use Otp\Domain\ValueObject\OtpPurpose;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Handler MfaChallengeHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MfaChallengeHandler implements CommandHandler
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes the handler.
     *
     * @since 1.0.0
     *
     * @param CommandBusPort $commandBus the command bus
     */
    public function __construct(
        private readonly CommandBusPort $commandBus,
    ) {
    }
    // #endregion

    // #region Methods
    /**
     * Method __invoke.
     *
     * Handles MFA challenge generation.
     *
     * @since 1.0.0
     *
     * @param MfaChallengeCommand $command the command
     *
     * @return MfaChallengeResult the result
     */
    public function __invoke(MfaChallengeCommand $command): MfaChallengeResult
    {
        /**
         * Result of the OTP generation.
         *
         * @var GenerateOtpResult $otpResult
         */
        $otpResult = $this->commandBus->dispatch(new GenerateOtpCommand(
            userId: $command->userId,
            purpose: OtpPurpose::from(value: $command->purpose),
            channel: OtpChannel::from(value: $command->channel),
            recipient: $command->recipient,
            ttlSeconds: $command->ttlSeconds,
        ));

        // Return the result
        return new MfaChallengeResult(
            challengeToken: $otpResult->token,
            maskedRecipient: $otpResult->maskedRecipient,
            expiresAt: $otpResult->expiresAt,
            maxAttempts: $otpResult->maxAttempts,
        );
    }
    // #endregion
}
