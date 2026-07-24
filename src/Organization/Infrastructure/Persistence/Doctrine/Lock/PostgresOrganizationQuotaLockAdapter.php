<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Lock;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Application\Port\Outbound\OrganizationQuotaLockPort;
use Organization\Domain\ValueObject\OrganizationQuotaResource;

/**
 * Adapter PostgresOrganizationQuotaLockAdapter.
 *
 * PostgreSQL implementation of the quota lock port using a transaction-scoped
 * advisory lock (`pg_advisory_xact_lock`). The lock lives on the same `main`
 * connection the create handlers run their transaction on, so acquiring it inside
 * the handler transaction serializes the quota count with the resource insert and
 * is released automatically on commit or rollback.
 *
 * The two-key form is fed the hashed organization id and resource name so each
 * (organization, resource) pair contends on its own lock; a hash collision would
 * only cause harmless extra serialization, never an incorrect result.
 *
 * @category Outbound Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PostgresOrganizationQuotaLockAdapter implements OrganizationQuotaLockPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the PostgresOrganizationQuotaLockAdapter class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the main entity manager whose connection carries the create transaction
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method acquire.
   * {@inheritdoc}
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param OrganizationQuotaResource $resource the capped resource being guarded
   */
  public function acquire(string $organizationId, OrganizationQuotaResource $resource): void
  {
    $connection = $this->entityManager->getConnection();

    // Advisory locks are Postgres-specific. On any other platform (e.g. the SQLite
    // test database) skip silently: the create path still works, it is simply not
    // lock-serialized there — production and dev both run Postgres.
    if (!$connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
      return;
    }

    $connection->executeQuery(
      'SELECT pg_advisory_xact_lock(hashtext(:organizationId), hashtext(:resource))',
      [
        'organizationId' => $organizationId,
        'resource' => $resource->value,
      ],
    );
  }
  // #endregion
}
