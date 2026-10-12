<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Adapter\Idempotency;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Otp\Application\Contract\Challenge\{ChallengeLifecycleProof, OtpPurpose};
use Otp\Application\Port\Inbound\Challenge\ChallengeLifecycleProofPort;

use function is_int;
use function is_numeric;
use function is_string;

/**
 * Reads current non-secret challenge facts directly from auth persistence.
 *
 * @category Adapter
 */
final readonly class ChallengeLifecycleProofAdapter implements ChallengeLifecycleProofPort
{
  /**
   * @param EntityManagerInterface $entityManager explicitly auth-owned persistence
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  /**
   * {@inheritDoc}
   */
  public function current(string $token): ?ChallengeLifecycleProof
  {
    return $this->read('challenge_token', $token);
  }

  /**
   * {@inheritDoc}
   */
  public function byId(string $challengeId): ?ChallengeLifecycleProof
  {
    return $this->read('id', $challengeId);
  }

  /**
   * @param 'challenge_token'|'id' $column fixed owner selector
   * @param string $value original proof or signed challenge identifier
   */
  private function read(string $column, string $value): ?ChallengeLifecycleProof
  {
    $row = $this->entityManager->getConnection()->fetchAssociative('SELECT id, user_id, purpose, recipient, channel, expires_at, verified_at, attempts, max_attempts FROM otps WHERE ' . $column . ' = ?', [$value]);
    $purpose = false === $row || !is_string($row['purpose']) ? null : OtpPurpose::tryFrom($row['purpose']);
    if (false === $row || null === $purpose || !is_string($row['id']) || !is_string($row['user_id']) || !is_string($row['recipient']) || !is_string($row['channel']) || !is_string($row['expires_at']) || (!is_int($row['attempts']) && !is_numeric($row['attempts'])) || (!is_int($row['max_attempts']) && !is_numeric($row['max_attempts']))) {
      return null;
    }

    return new ChallengeLifecycleProof((string) $row['id'], (string) $row['user_id'], $purpose, (string) $row['recipient'], (string) $row['channel'], (is_string($row['verified_at']) ? 'verified' : ((int) $row['attempts'] >= (int) $row['max_attempts'] ? 'exhausted' : 'pending')), new DateTimeImmutable((string) $row['expires_at']), is_string($row['verified_at']) ? new DateTimeImmutable($row['verified_at']) : null, (int) $row['attempts']);
  }
}
