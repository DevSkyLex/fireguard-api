<?php

declare(strict_types=1);

namespace Otp\Domain\Model;

use Otp\Domain\ValueObject\{ChallengeToken, OtpChannel, OtpId, OtpPurpose};

/** Persisted identity and delivery context of an OTP challenge. */
final readonly class OtpRestoredIdentity
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries the identifier, purpose and delivery context of a persisted challenge.
   *
   * @access public
   *
   * @param OtpId $id oTP challenge identifier
   * @param ChallengeToken $challengeToken challenge token used to address the challenge
   * @param string $userId user who owns the challenge
   * @param OtpPurpose $purpose purpose for which the challenge was issued
   * @param OtpChannel $channel channel used to deliver the challenge
   * @param string $recipient recipient address associated with the challenge
   *
   * @return void
   */
  public function __construct(
    public OtpId $id,
    public ChallengeToken $challengeToken,
    public string $userId,
    public OtpPurpose $purpose,
    public OtpChannel $channel,
    public string $recipient,
  ) {
  }
  // #endregion
}
