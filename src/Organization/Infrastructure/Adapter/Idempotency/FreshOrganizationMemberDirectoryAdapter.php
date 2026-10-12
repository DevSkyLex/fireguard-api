<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\{ArrayParameterType, Connection};
use Organization\Application\Contract\Member\OrganizationMemberView;
use Organization\Application\Port\Inbound\FreshOrganizationMemberDirectoryPort;

use function in_array;
use function is_string;

/**
 * Class FreshOrganizationMemberDirectoryAdapter
 *
 * Reads membership identities and current status directly from their owning main rows.
 *
 * @category Adapter
 */
final readonly class FreshOrganizationMemberDirectoryAdapter implements FreshOrganizationMemberDirectoryPort
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
  public function findById(string $memberId): ?OrganizationMemberView
  {
    return $this->view($this->connection->fetchAssociative('SELECT id, organization_id, user_id, is_active FROM organization_members WHERE id = :id', ['id' => $memberId]));
  }

  /**
   * {@inheritdoc}
   */
  public function findByOrganizationAndUser(string $organizationId, string $userId): ?OrganizationMemberView
  {
    return $this->view($this->connection->fetchAssociative('SELECT id, organization_id, user_id, is_active FROM organization_members WHERE organization_id = :organizationId AND user_id = :userId', ['organizationId' => $organizationId, 'userId' => $userId]));
  }

  /**
   * {@inheritdoc}
   */
  public function findUserIdsByMemberIds(string $organizationId, array $memberIds, bool $activeOnly = false): array
  {
    if ([] === $memberIds) {
      return [];
    }
    $rows = $this->connection->fetchAllAssociative('SELECT id, user_id FROM organization_members WHERE organization_id = :organizationId AND id IN (:ids)' . ($activeOnly ? ' AND is_active = TRUE' : ''), ['organizationId' => $organizationId, 'ids' => $memberIds], ['ids' => ArrayParameterType::STRING]);
    $users = [];
    foreach ($rows as $row) {
      if (is_string($row['id'] ?? null) && is_string($row['user_id'] ?? null)) {
        $users[$row['id']] = $row['user_id'];
      }
    }

    return $users;
  }

  /**
   * @param array<string,mixed>|false $row current stored scalar membership
   *
   * @return OrganizationMemberView|null validated public identity facts
   */
  private function view(array|false $row): ?OrganizationMemberView
  {
    if (false === $row || !is_string($row['id'] ?? null) || !is_string($row['organization_id'] ?? null) || !is_string($row['user_id'] ?? null)) {
      return null;
    }

    return new OrganizationMemberView($row['id'], $row['organization_id'], $row['user_id'], in_array($row['is_active'] ?? false, [true, 1, '1', 't'], true));
  }
  // #endregion
}
