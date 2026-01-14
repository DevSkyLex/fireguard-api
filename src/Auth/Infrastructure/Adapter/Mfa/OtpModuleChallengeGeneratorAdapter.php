<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Mfa;

use Auth\Application\Port\Outbound\Mfa\ChallengeGeneratorPort;
use Auth\Application\UseCase\Command\Mfa\MfaChallenge\{MfaChallengeCommand, MfaChallengeResult};
use Otp\Application\Contract\Challenge\{ChallengeInfo, OtpChannel, OtpPurpose};
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;

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
   * @param OtpChallengePort $challengePort the OTP challenge port
   */
  public function __construct(
    private OtpChallengePort $challengePort,
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
    /** @var ChallengeInfo $result */
    $result = $this->challengePort->generate(
      userId: $command->userId,
      purpose: OtpPurpose::from($command->purpose),
      channel: OtpChannel::from($command->channel),
      recipient: $command->recipient,
      ttlSeconds: $command->ttlSeconds,
    );

    return new MfaChallengeResult(
      challengeToken: $result->challengeToken,
      maskedRecipient: $result->maskedRecipient,
      expiresAt: $result->expiresAt,
      maxAttempts: $result->maxAttempts,
    );
  }
  // #endregion
}
