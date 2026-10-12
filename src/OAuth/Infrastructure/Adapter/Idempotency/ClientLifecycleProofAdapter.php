<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use OAuth\Application\Contract\Client\ClientLifecycleProof;
use OAuth\Application\Port\Inbound\ClientLifecycleProofPort;
use Shared\Application\Port\Outbound\HashingPort;
use Shared\Application\Port\Outbound\Idempotency\IdempotencyFingerprintPort;
use Shared\Domain\ValueObject\HashedSecret;

use function is_int;
use function is_string;
use function preg_match;

/**
 * Reads the current auth-owned epoch and purpose-separated secret fingerprint without identity caches.
 *
 * @category Adapter
 */
final readonly class ClientLifecycleProofAdapter implements ClientLifecycleProofPort
{
  public function __construct(private Connection $connection, private IdempotencyFingerprintPort $fingerprints, private HashingPort $hashing)
  {
  }

  public function current(string $clientId): ?ClientLifecycleProof
  {
    if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di', $clientId)) {
      return null;
    }
    $row = $this->connection->fetchAssociative('SELECT id, security_revision, secret, is_active, deleted_at FROM clients WHERE id = ?', [$clientId]);
    $revision = false === $row ? null : ($row['security_revision'] ?? null);
    if (false === $row || !is_string($row['id'] ?? null) || !is_string($row['secret'] ?? null) || !$this->validRevision($revision)) {
      return null;
    }

    return new ClientLifecycleProof($row['id'], (int) $revision, true === $row['is_active'], null !== $row['deleted_at'], $this->fingerprints->digest('oauth.client.secret.lineage', [$row['id'], $row['secret']]));
  }

  public function matchesSecret(string $clientId, string $plainSecret): bool
  {
    $row = $this->connection->fetchAssociative('SELECT secret, deleted_at FROM clients WHERE id = ?', [$clientId]);
    if (false === $row || !is_string($row['secret'] ?? null) || null !== ($row['deleted_at'] ?? null)) {
      return false;
    }

    return $this->hashing->verify($plainSecret, new HashedSecret($row['secret']));
  }

  /**
   * Method validRevision
   *
   * Accepts only positive integer epochs represented by the auth driver.
   *
   * @access private
   *
   * @param mixed $revision auth driver scalar epoch
   *
   * @return bool whether the epoch is a positive ASCII decimal integer
   *
   * @phpstan-assert-if-true int|string $revision
   */
  private function validRevision(mixed $revision): bool
  {
    return (is_int($revision) || (is_string($revision) && 1 === preg_match('/^[1-9]\d*$/D', $revision))) && (int) $revision >= 1;
  }
}
