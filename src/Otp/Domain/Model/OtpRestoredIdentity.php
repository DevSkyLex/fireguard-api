<?php

declare(strict_types=1);

namespace Otp\Domain\Model;

use Otp\Domain\ValueObject\{ChallengeToken, OtpChannel, OtpId, OtpPurpose};

/** Persisted identity and delivery context of an OTP challenge. */
final readonly class OtpRestoredIdentity
{
  public function __construct(
    public OtpId $id,
    public ChallengeToken $challengeToken,
    public string $userId,
    public OtpPurpose $purpose,
    public OtpChannel $channel,
    public string $recipient,
  ) {
  }
}
