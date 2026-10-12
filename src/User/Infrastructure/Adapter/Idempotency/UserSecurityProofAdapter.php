<?php

declare(strict_types=1);

namespace User\Infrastructure\Adapter\Idempotency;

use Auth\Application\Port\Inbound\SecurityUserCacheInvalidationPort;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use User\Application\Contract\User\UserSecurityProof;
use User\Application\Port\Inbound\{UserCredentialProofPort, UserSecurityRevisionPort};
use User\Infrastructure\Persistence\Doctrine\Record\UserRecord;

use function filter_var;
use function is_int;
use function is_numeric;
use function is_string;
use function password_verify;
use function strtolower;
use function trim;

use const FILTER_VALIDATE_EMAIL;

/**
 * Class UserSecurityProofAdapter
 *
 * Reads fresh auth rows for credential replay authorization and advances revision under the owning account lock.
 *
 * @category Adapter
 */
final readonly class UserSecurityProofAdapter implements UserSecurityRevisionPort, UserCredentialProofPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager explicitly auth-owned identity map
   * @param ?SecurityUserCacheInvalidationPort $cache optional current-principal cache invalidation
   *
   * @return void
   */
  public function __construct(private EntityManagerInterface $entityManager, private ?SecurityUserCacheInvalidationPort $cache = null)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function current(string $userId): ?UserSecurityProof
  {
    $row = $this->entityManager->getConnection()->fetchAssociative('SELECT id, status, email_verified, security_revision, tenant_id FROM users WHERE id = ?', [$userId]);

    return false === $row ? null : $this->proof($row);
  }

  /**
   * {@inheritDoc}
   */
  public function verify(string $identifier, string $password, bool $requireEmailVerification = true): ?UserSecurityProof
  {
    $identifier = trim($identifier);
    $email = false !== filter_var($identifier, FILTER_VALIDATE_EMAIL);
    $row = $this->entityManager->getConnection()->fetchAssociative(
      'SELECT id, password, status, email_verified, security_revision, tenant_id FROM users WHERE ' . ($email ? 'email' : 'username') . ' = ?',
      [$email ? strtolower($identifier) : $identifier],
    );
    if (false === $row || !is_string($row['password'] ?? null) || !password_verify($password, $row['password'])) {
      return null;
    }
    $status = $row['status'] ?? null;
    if ('active' !== $status && ($requireEmailVerification || 'pending_verification' !== $status)) {
      return null;
    }

    return $this->proof($row);
  }

  /**
   * {@inheritDoc}
   */
  public function advance(string $userId): int
  {
    $connection = $this->entityManager->getConnection();
    if (!$connection->isTransactionActive()) {
      throw new LogicException('User security revision must advance inside its owning auth transaction.');
    }
    $revision = $connection->fetchOne('UPDATE users SET security_revision = security_revision + 1 WHERE id = ? RETURNING security_revision', [$userId]);
    if ((!is_int($revision) && !is_numeric($revision)) || (int) $revision < 1) {
      throw new LogicException('Cannot advance security revision for an absent account.');
    }
    $managed = $this->entityManager->find(UserRecord::class, $userId);
    if ($managed instanceof UserRecord) {
      $this->entityManager->refresh($managed);
    }
    $this->cache?->invalidateUser($userId);

    return (int) $revision;
  }

  /**
   * Method proof
   *
   * Reduces scalar persistence facts to the published owner proof.
   *
   * @access private
   *
   * @param array<string, mixed> $row current auth scalar row
   *
   * @return ?UserSecurityProof validated owner proof
   */
  private function proof(array $row): ?UserSecurityProof
  {
    $id = $row['id'] ?? null;
    $revision = $row['security_revision'] ?? null;
    if (!is_string($id) || (!is_int($revision) && !is_numeric($revision)) || (int) $revision < 1) {
      return null;
    }

    return new UserSecurityProof($id, (int) $revision, 'active' === ($row['status'] ?? null), true === ($row['email_verified'] ?? null) || 1 === ($row['email_verified'] ?? null) || '1' === ($row['email_verified'] ?? null), is_string($row['tenant_id'] ?? null) ? $row['tenant_id'] : null);
  }
  // #endregion
}
