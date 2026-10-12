<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Organization\Application\Contract\Workforce\{OrganizationWorkforceContext, OrganizationWorkforceMember};
use Organization\Application\Port\Inbound\{FreshOrganizationWorkforceDirectoryPort, OrganizationWorkforceDirectoryPort};
use Organization\Domain\ValueObject\OrganizationRegionalSettings;

use function in_array;
use function is_array;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Class FreshOrganizationWorkforceDirectoryAdapter
 *
 * Reads authorization-bearing membership and settings without managed entities while retaining existing presentation directory projections.
 *
 * @category Adapter
 */
final readonly class FreshOrganizationWorkforceDirectoryAdapter implements FreshOrganizationWorkforceDirectoryPort
{
  // #region Constructor
  /**
   * @param Connection $connection explicitly selected main connection
   * @param OrganizationWorkforceDirectoryPort $presentation published owner label projections
   */
  public function __construct(private Connection $connection, private OrganizationWorkforceDirectoryPort $presentation)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function context(string $organizationId): ?OrganizationWorkforceContext
  {
    $stored = $this->connection->fetchOne('SELECT settings FROM organizations WHERE id = :id', ['id' => $organizationId]);
    if (false === $stored) {
      return null;
    }
    $data = is_string($stored) ? json_decode($stored, true, 16, JSON_THROW_ON_ERROR) : $stored;
    if (!is_array($data) || !is_array($data['regional'] ?? [])) {
      return null;
    }
    $regional = OrganizationRegionalSettings::fromArray($data['regional'] ?? []);

    return new OrganizationWorkforceContext($regional->timezone, $regional->firstDayOfWeek);
  }

  /**
   * {@inheritdoc}
   */
  public function members(string $organizationId): array
  {
    $rows = $this->connection->fetchAllAssociative('SELECT id, user_id, is_active FROM organization_members WHERE organization_id = :id ORDER BY id', ['id' => $organizationId]);
    $members = [];
    foreach ($rows as $row) {
      if (is_string($row['id'] ?? null) && is_string($row['user_id'] ?? null)) {
        $members[] = new OrganizationWorkforceMember($row['id'], $row['user_id'], in_array($row['is_active'] ?? false, [true, 1, '1', 't'], true));
      }
    }

    return $members;
  }

  /**
   * {@inheritdoc}
   */
  public function labels(string $organizationId, array $memberIds): array
  {
    return $this->presentation->labels($organizationId, $memberIds);
  }

  /**
   * {@inheritdoc}
   */
  public function profiles(string $organizationId, array $memberIds): array
  {
    return $this->presentation->profiles($organizationId, $memberIds);
  }

  /**
   * {@inheritdoc}
   */
  public function teams(string $organizationId): array
  {
    return $this->presentation->teams($organizationId);
  }
  // #endregion
}
