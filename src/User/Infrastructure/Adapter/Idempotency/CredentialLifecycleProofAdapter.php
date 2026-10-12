<?php

declare(strict_types=1);

namespace User\Infrastructure\Adapter\Idempotency;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use User\Application\Contract\User\CredentialLifecycleProof;
use User\Application\Port\Inbound\CredentialLifecycleProofPort;

use function is_int;
use function is_numeric;
use function is_string;
use function password_verify;
use function strtolower;
use function trim;

/**
 * Reads auth-owned lifecycle facts freshly and keeps credential digests private.
 *
 * @category Adapter
 */
final readonly class CredentialLifecycleProofAdapter implements CredentialLifecycleProofPort
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
  public function current(string $userId): ?CredentialLifecycleProof
  {
    return $this->read('id', $userId);
  }

  /**
   * {@inheritDoc}
   */
  public function byEmail(string $email): ?CredentialLifecycleProof
  {
    return $this->read('email', strtolower(trim($email)));
  }

  /**
   * {@inheritDoc}
   */
  public function verifyPassword(string $userId, string $password): bool
  {
    $digest = $this->entityManager->getConnection()->fetchOne('SELECT password FROM users WHERE id = ?', [$userId]);

    return is_string($digest) && password_verify($password, $digest);
  }

  /**
   * @param 'id'|'email' $column fixed owner selector
   * @param string $value requested identity
   */
  private function read(string $column, string $value): ?CredentialLifecycleProof
  {
    $row = $this->entityManager->getConnection()->fetchAssociative('SELECT id, status, email, email_verified, email_ownership_verified_at, security_revision, password IS NOT NULL AS password_configured, failed_login_attempts FROM users WHERE ' . $column . ' = ?', [$value]);
    if (false === $row || !is_string($row['id']) || !is_string($row['status']) || !is_string($row['email']) || (!is_int($row['security_revision']) && !is_numeric($row['security_revision'])) || (int) $row['security_revision'] < 1 || (!is_int($row['failed_login_attempts']) && !is_numeric($row['failed_login_attempts']))) {
      return null;
    }

    return new CredentialLifecycleProof((string) $row['id'], (int) $row['security_revision'], (string) $row['status'], (string) $row['email'], true === $row['email_verified'] || 1 === $row['email_verified'] || '1' === $row['email_verified'], true === $row['password_configured'] || 1 === $row['password_configured'] || '1' === $row['password_configured'], (int) $row['failed_login_attempts'], is_string($row['email_ownership_verified_at']) ? new DateTimeImmutable($row['email_ownership_verified_at']) : null);
  }
}
