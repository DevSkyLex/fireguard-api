<?php

declare(strict_types=1);

namespace Procurement\Infrastructure\Adapter\Doctrine;

use Doctrine\DBAL\{Connection, ParameterType};
use Procurement\Domain\Exception\ProcurementException;

use function array_keys;
use function array_map;
use function implode;
use function in_array;

/**
 * Class ProcurementTableStorageAdapter
 *
 * Applies scoped SQL table primitives and preserves immutable physical evidence on updates.
 * Composes the repository internally with its explicitly main connection and enclosing transaction.
 *
 * @category Adapter
 */
final readonly class ProcurementTableStorageAdapter
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param Connection $connection the connection value
   *
   * @return void
   */
  public function __construct(private Connection $connection)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method one
   *
   * Scopes a retained row to the owning organization.
   *
   * @access public
   *
   * @param string $table the table value
   * @param string $organizationId the organizationId value
   * @param string $id the id value
   *
   * @return array<string,mixed>|null
   */
  public function one(string $table, string $organizationId, string $id): ?array
  {
    $row = $this->connection->fetchAssociative('SELECT * FROM ' . $table . ' WHERE organization_id = :org AND id = :id', ['org' => $organizationId, 'id' => $id]);

    return false === $row ? null : $row;
  }

  /**
   * Method upsert
   *
   * Retains physical identity columns while updating only permitted reconciliation fields.
   *
   * @access public
   *
   * @param array<string,mixed> $values
   * @param string $table the table value
   *
   * @return void
   */
  public function upsert(string $table, array $values): void
  {
    $names = array_keys($values);
    $updates = [];
    foreach ($names as $name) {
      if (!in_array($name, ['id', 'organization_id', 'created_at'], true) && ('procurement_receipts' !== $table || in_array($name, ['inventory_movement_id', 'equipment_ids', 'returned_quantity', 'pending_return_quantity', 'blocked_reason', 'revision'], true)) && ('procurement_returns' !== $table || in_array($name, ['status', 'inventory_movement_id', 'blocked_reason', 'reconciled_at', 'revision'], true))) {
        $updates[] = $name . ' = EXCLUDED.' . $name;
      }
    }
    $affected = $this->connection->executeStatement('INSERT INTO ' . $table . ' (' . implode(', ', $names) . ') VALUES (' . implode(', ', array_map(static fn (string $name): string => ':' . $name, $names)) . ') ON CONFLICT (id) DO UPDATE SET ' . implode(', ', $updates) . ' WHERE ' . $table . '.organization_id = EXCLUDED.organization_id', $values);
    if (1 !== $affected) {
      throw ProcurementException::notFound();
    }
  }

  /**
   * Method page
   *
   * Applies the existing deterministic sort and bounded integer pagination.
   *
   * @access public
   *
   * @param array<string,mixed> $parameters
   * @param string $table the table value
   * @param string $criteria the criteria value
   * @param int $offset the offset value
   * @param int $limit the limit value
   * @param string $sort the sort value
   *
   * @return list<array<string,mixed>>
   */
  public function page(string $table, string $criteria, array $parameters, int $offset, int $limit, string $sort): array
  {
    return $this->connection->fetchAllAssociative('SELECT * FROM ' . $table . ' WHERE ' . $criteria . ' ORDER BY ' . $sort . ' LIMIT :limit OFFSET :offset', $parameters + ['limit' => $limit, 'offset' => $offset], ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER]);
  }

  /**
   * Method supplierCriteria
   *
   * Shares archive and search predicates between supplier count and collection reads.
   *
   * @access public
   *
   * @param ?bool $archived the archived value
   *
   * @return string
   */
  public function supplierCriteria(?bool $archived): string
  {
    $criteria = 'organization_id = :org';
    if (null !== $archived) {
      $criteria .= $archived ? ' AND archived_at IS NOT NULL' : ' AND archived_at IS NULL';
    }

    return $criteria . " AND (name ILIKE :search OR COALESCE(code, '') ILIKE :search)";
  }

  /**
   * Method orderCriteria
   *
   * Shares scoped lifecycle and supplier filters between order count and collection reads.
   *
   * @access public
   *
   * @param string $organizationId the organizationId value
   * @param ?string $status the status value
   * @param ?string $supplierId the supplierId value
   *
   * @return array{string,array<string,string>}
   */
  public function orderCriteria(string $organizationId, ?string $status, ?string $supplierId): array
  {
    $criteria = 'organization_id = :org';
    $parameters = ['org' => $organizationId];
    if (null !== $status) {
      $criteria .= ' AND status = :status';
      $parameters['status'] = $status;
    }
    if (null !== $supplierId) {
      $criteria .= ' AND supplier_id = :supplier';
      $parameters['supplier'] = $supplierId;
    }

    return [$criteria, $parameters];
  }


  // #endregion
}
