<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Read;

use Doctrine\DBAL\{ArrayParameterType, Connection};
use Organization\Application\Contract\Member\OrganizationMemberView;
use Organization\Application\Port\Inbound\OrganizationMemberDirectoryPort;

/**
 * Adapter OrganizationMemberDirectoryAdapter.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationMemberDirectoryAdapter implements OrganizationMemberDirectoryPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param Connection $connection the main database connection
   */
  public function __construct(private Connection $connection)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function findById(string $memberId): ?OrganizationMemberView
  {
    /** @var array{id: string, organization_id: string, user_id: string, is_active: bool|string|int}|false $row */
    $row = $this->connection->fetchAssociative('SELECT id, organization_id, user_id, is_active FROM organization_members WHERE id = :id', ['id' => $memberId]);

    return false === $row ? null : $this->view($row);
  }

  /**
   * {@inheritDoc}
   */
  public function findByOrganizationAndUser(string $organizationId, string $userId): ?OrganizationMemberView
  {
    /** @var array{id: string, organization_id: string, user_id: string, is_active: bool|string|int}|false $row */
    $row = $this->connection->fetchAssociative('SELECT id, organization_id, user_id, is_active FROM organization_members WHERE organization_id = :organization AND user_id = :user', ['organization' => $organizationId, 'user' => $userId]);

    return false === $row ? null : $this->view($row);
  }

  /**
   * {@inheritDoc}
   */
  public function findUserIdsByMemberIds(string $organizationId, array $memberIds, bool $activeOnly = false): array
  {
    if ([] === $memberIds) {
      return [];
    }
    /** @var array<string, string> $identities */
    $identities = $this->connection->fetchAllKeyValue('SELECT id, user_id FROM organization_members WHERE organization_id = :organization AND id IN (:members)' . ($activeOnly ? ' AND is_active = TRUE' : ''), ['organization' => $organizationId, 'members' => $memberIds], ['members' => ArrayParameterType::STRING]);

    return $identities;
  }

  /**
   * Projects persistence facts without leaking a Record or Domain identifier.
   *
   * @since 1.0.0
   *
   * @param array{id: string, organization_id: string, user_id: string, is_active: bool|string|int} $row the scalar database row
   *
   * @return OrganizationMemberView the public membership facts
   */
  private function view(array $row): OrganizationMemberView
  {
    return new OrganizationMemberView($row['id'], $row['organization_id'], $row['user_id'], true === $row['is_active'] || 't' === $row['is_active'] || 1 === $row['is_active']);
  }
  // #endregion
}
