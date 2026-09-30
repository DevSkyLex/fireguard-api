<?php

declare(strict_types=1);

namespace Otp\Application\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use Otp\Application\Contract\Challenge\{ChallengeInfo, OtpChannel, OtpPurpose, VerificationInfo};
use Otp\Application\Exception\OtpNotFoundException;
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;
use Otp\Application\UseCase\Command\Challenge\GenerateOtp\{GenerateOtpCommand, GenerateOtpHandler};
use Otp\Application\UseCase\Command\Challenge\VerifyOtp\{VerifyOtpCommand, VerifyOtpHandler};
use Otp\Domain\ValueObject\{OtpChannel as DomainOtpChannel, OtpPurpose as DomainOtpPurpose};

use function bin2hex;
use function random_bytes;
use function sprintf;

/**
 * Service OtpChallengeService.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OtpChallengeService implements OtpChallengePort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param GenerateOtpHandler $generateHandler the OTP generation handler
   * @param VerifyOtpHandler $verifyHandler the OTP verification handler
   */
  public function __construct(
    private GenerateOtpHandler $generateHandler,
    private VerifyOtpHandler $verifyHandler,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method generate
   *
   * Creates a verification challenge and returns its public token, masked recipient and expiry details.
   *
   * @access public
   *
   * @param string $userId the user identifier
   * @param OtpPurpose $purpose the purpose
   * @param OtpChannel $channel the channel
   * @param string $recipient the recipient
   * @param ?int $ttlSeconds the optional ttl seconds
   * @param ?int $maxAttempts the optional max attempts
   *
   * @return ChallengeInfo
   */
  public function generate(
    string $userId,
    OtpPurpose $purpose,
    OtpChannel $channel,
    string $recipient,
    ?int $ttlSeconds = null,
    ?int $maxAttempts = null,
  ): ChallengeInfo {
    $command = new GenerateOtpCommand(
      userId: $userId,
      purpose: $purpose,
      channel: $channel,
      recipient: $recipient,
      ttlSeconds: $ttlSeconds,
      maxAttempts: $maxAttempts,
    );

    $result = $this->generateHandler->__invoke($command);

    return new ChallengeInfo(
      challengeToken: $result->token,
      maskedRecipient: $result->maskedRecipient,
      expiresAt: $result->expiresAt,
      maxAttempts: $result->maxAttempts,
    );
  }

  /**
   * Method generateDecoy
   *
   * Returns a challenge-shaped decoy without storing a code or sending a message, keeping account-existence responses indistinguishable.
   *
   * @access public
   *
   * @param OtpPurpose $purpose the purpose
   * @param OtpChannel $channel the channel
   * @param string $recipient the recipient
   *
   * @return ChallengeInfo
   */
  public function generateDecoy(
    OtpPurpose $purpose,
    OtpChannel $channel,
    string $recipient,
  ): ChallengeInfo {
    $domainPurpose = DomainOtpPurpose::from(value: $purpose->value);

    return new ChallengeInfo(
      // Same shape and entropy as a real `ChallengeToken`, so the two are not
      // distinguishable by length or alphabet either.
      challengeToken: bin2hex(random_bytes(32)),
      maskedRecipient: DomainOtpChannel::from(value: $channel->value)->mask(recipient: $recipient),
      expiresAt: new DateTimeImmutable(
        datetime: sprintf('+%d seconds', $domainPurpose->getDefaultTtlSeconds()),
      ),
      maxAttempts: $domainPurpose->getDefaultMaxAttempts(),
    );
  }

  /**
   * Method verify
   *
   * Verifies the submitted challenge token and code and returns the verification outcome.
   *
   * @access public
   *
   * @param string $challengeToken the public challenge token
   * @param string $code the submitted code
   *
   * @return VerificationInfo the verification outcome
   */
  public function verify(string $challengeToken, string $code): VerificationInfo
  {
    return $this->verifyCommand(new VerifyOtpCommand(
      code: $code,
      challengeToken: $challengeToken,
    ));
  }

  /**
   * Method verifyFor
   *
   * Verifies the challenge while also binding it to the expected user, purpose and optional current recipient.
   *
   * @access public
   *
   * @param string $challengeToken the public challenge token
   * @param string $code the submitted code
   * @param string $userId the user identifier
   * @param OtpPurpose $purpose the purpose
   * @param ?string $recipient the optional recipient
   *
   * @return VerificationInfo
   */
  public function verifyFor(
    string $challengeToken,
    string $code,
    string $userId,
    OtpPurpose $purpose,
    ?string $recipient = null,
  ): VerificationInfo {
    return $this->verifyCommand(new VerifyOtpCommand(
      code: $code,
      challengeToken: $challengeToken,
      expectedUserId: $userId,
      expectedPurpose: DomainOtpPurpose::from($purpose->value),
      expectedRecipient: $recipient,
    ));
  }

  /**
   * Method verifyCommand
   *
   * Maps missing challenges and invalid request values to the neutral verification failure returned to callers.
   *
   * @access private
   *
   * @param VerifyOtpCommand $command the command to handle
   *
   * @return VerificationInfo
   */
  private function verifyCommand(VerifyOtpCommand $command): VerificationInfo
  {
    try {
      $result = $this->verifyHandler->__invoke($command);
    } catch (OtpNotFoundException|InvalidArgumentException) {
      return new VerificationInfo(
        success: false,
        attemptsRemaining: 0,
        error: 'Challenge not found.',
        errorCode: 'invalid_token',
      );
    }

    return new VerificationInfo(
      success: $result->success,
      attemptsRemaining: $result->attemptsRemaining,
      error: $result->error,
      errorCode: match ($result->error) {
        null => null,
        'OTP has expired.' => 'expired',
        'Maximum verification attempts exceeded.' => 'max_attempts_exceeded',
        default => 'invalid_code',
      },
    );
  }
  // #endregion
}
