<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Adapter\Idempotency;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Otp\Application\Contract\Challenge\{ChallengeReplayProof, OtpPurpose};
use Otp\Application\Port\Inbound\Challenge\ChallengeReplayProofPort;

use function is_int;
use function is_numeric;
use function is_string;

/**
 * Reads current challenge ownership without a stale ORM identity-map result.
 *
 * @category Adapter
 */
final readonly class ChallengeReplayProofAdapter implements ChallengeReplayProofPort
{
  /**
   * @param EntityManagerInterface $entityManager explicitly auth-bound persistence
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  /**
   * {@inheritDoc}
   */
  public function current(string $token, string $userId, OtpPurpose $purpose): ?ChallengeReplayProof
  {
    $row = $this->entityManager->getConnection()->fetchAssociative('SELECT id, user_id, expires_at, verified_at, attempts FROM otps WHERE challenge_token = :token AND user_id = :user AND purpose = :purpose', ['token' => $token, 'user' => $userId, 'purpose' => $purpose->value]);
    if (false === $row || !is_string($row['id']) || !is_string($row['user_id']) || !is_string($row['expires_at']) || (!is_int($row['attempts']) && !is_numeric($row['attempts']))) {
      return null;
    }

    return new ChallengeReplayProof($row['id'], $row['user_id'], $purpose, new DateTimeImmutable($row['expires_at']), is_string($row['verified_at']) ? new DateTimeImmutable($row['verified_at']) : null, (int) $row['attempts']);
  }
}
