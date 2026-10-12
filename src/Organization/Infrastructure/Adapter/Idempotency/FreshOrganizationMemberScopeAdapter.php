<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Organization\Application\Port\Inbound\FreshOrganizationMemberScopePort;

use function is_string;

/**
 * Class FreshOrganizationMemberScopeAdapter
 *
 * Reads current owner membership scope directly from the main connection.
 *
 * @category Adapter
 */
final readonly class FreshOrganizationMemberScopeAdapter implements FreshOrganizationMemberScopePort
{
  // #region Constructor
  /**
   * @param Connection $connection explicitly selected main connection
   */
  public function __construct(private Connection $connection)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function organizationId(string $memberId): ?string
  {
    $organization = $this->connection->fetchOne('SELECT organization_id FROM organization_members WHERE id = :id', ['id' => $memberId]);

    return is_string($organization) ? $organization : null;
  }

  /**
   * {@inheritdoc}
   */
  public function isActive(string $memberId, string $organizationId): bool
  {
    return false !== $this->connection->fetchOne('SELECT id FROM organization_members WHERE id = :id AND organization_id = :organizationId AND is_active = TRUE', ['id' => $memberId, 'organizationId' => $organizationId]);
  }
  // #endregion
}
