<?php

declare(strict_types=1);

namespace Otp\Application\Service;

use DateTimeImmutable;
use Otp\Application\Contract\Challenge\{ChallengeState, OtpChannel, OtpPurpose};
use Otp\Application\Port\Inbound\Challenge\ChallengeAccessPort;
use Otp\Application\Port\Outbound\Internal\Challenge\OtpRepositoryPort;
use Otp\Domain\ValueObject\ChallengeToken;

/**
 * Builds immutable challenge projections inside the owning module.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ChallengeAccessService implements ChallengeAccessPort
{
  /**
   * @param OtpRepositoryPort $challenges the owned repository
   */
  public function __construct(private OtpRepositoryPort $challenges)
  {
  }

  /**
   * {@inheritDoc}
   */
  public function find(string $token): ?ChallengeState
  {
    $challenge = $this->challenges->findByChallengeToken(ChallengeToken::fromString($token));

    return null === $challenge ? null : new ChallengeState(
      userId: $challenge->userId(),
      purpose: OtpPurpose::from($challenge->purpose()->value),
      channel: OtpChannel::from($challenge->channel()->value),
      recipient: $challenge->recipient(),
      status: $challenge->status(),
      createdAt: $challenge->createdAt(),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function canResendIn(DateTimeImmutable $createdAt, DateTimeImmutable $now): int
  {
    return ChallengeResendPolicy::canResendIn($createdAt, $now);
  }
}
