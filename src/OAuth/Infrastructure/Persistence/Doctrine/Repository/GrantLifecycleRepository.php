<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use OAuth\Application\Port\Outbound\Token\GrantLifecyclePort;
use Throwable;

use function is_string;

/**
 * Class GrantLifecycleRepository
 *
 * Holds a per-user PostgreSQL transaction lock across grant validation and token persistence.
 *
 * @category Repository
 */
final readonly class GrantLifecycleRepository implements GrantLifecyclePort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @param EntityManagerInterface $entityManager the explicit auth entity manager
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method transactional
   * {@inheritDoc}
   *
   * Clears rolled-back managed state so a later audit write cannot resurrect token rows.
   */
  public function transactional(callable $operation): mixed
  {
    try {
      return $this->entityManager->getConnection()->transactional(function () use ($operation): mixed {
        $result = $operation();
        $this->entityManager->flush();

        return $result;
      });
    } catch (Throwable $exception) {
      $this->entityManager->clear();

      throw $exception;
    }
  }

  /**
   * Method lockUser
   * {@inheritDoc}
   */
  public function lockUser(string $userId): void
  {
    $connection = $this->entityManager->getConnection();
    if (!$connection->isTransactionActive()) {
      throw new LogicException('OAuth user grant locking requires an auth transaction.');
    }
    $connection->executeQuery('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['fireguard.oauth.user:' . $userId]);
  }

  /**
   * Method isAuthCodeRevoked
   * {@inheritDoc}
   */
  public function isAuthCodeRevoked(string $identifier): bool
  {
    $connection = $this->entityManager->getConnection();
    $owner = $connection->fetchOne('SELECT user_identifier FROM auth_codes WHERE identifier = ?', [$identifier]);
    if (!is_string($owner) || '' === $owner) {
      return true;
    }
    $this->lockUser($owner);

    return !is_string($connection->fetchOne('SELECT identifier FROM auth_codes WHERE identifier = ? AND user_identifier = ? AND is_revoked = false AND expiry > CURRENT_TIMESTAMP', [$identifier, $owner]));
  }

  /**
   * Method isRefreshTokenRevoked
   * {@inheritDoc}
   */
  public function isRefreshTokenRevoked(string $identifier): bool
  {
    $connection = $this->entityManager->getConnection();
    $row = $connection->fetchAssociative('SELECT a.user_identifier FROM refresh_tokens r INNER JOIN access_tokens a ON a.identifier = r.access_token_identifier WHERE r.identifier = ?', [$identifier]);
    if (false === $row) {
      return true;
    }
    $owner = $row['user_identifier'];
    if (is_string($owner) && '' !== $owner) {
      $this->lockUser($owner);
    }

    return !is_string($connection->fetchOne('SELECT identifier FROM refresh_tokens WHERE identifier = ? AND is_revoked = false AND expiry > CURRENT_TIMESTAMP', [$identifier]));
  }

  /**
   * Method isAccessTokenUsable
   * {@inheritDoc}
   */
  public function isAccessTokenUsable(string $identifier, string $userId): bool
  {
    return is_string($this->entityManager->getConnection()->fetchOne('SELECT identifier FROM access_tokens WHERE identifier = ? AND user_identifier = ? AND is_revoked = false AND expiry > CURRENT_TIMESTAMP', [$identifier, $userId]));
  }
  // #endregion
}
