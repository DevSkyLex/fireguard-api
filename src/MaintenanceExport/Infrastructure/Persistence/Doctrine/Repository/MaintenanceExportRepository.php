<?php

declare(strict_types=1);

namespace MaintenanceExport\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\DBAL\{Connection, Exception\UniqueConstraintViolationException, ParameterType};
use LogicException;
use MaintenanceExport\Application\Contract\{ExportDocumentSummary, ExportOperation};
use MaintenanceExport\Application\Port\Outbound\MaintenanceExportRepositoryPort;
use MaintenanceExport\Domain\Exception\MaintenanceExportException;
use MaintenanceExport\Domain\Model\{ExportDocument, ExternalReference};
use MaintenanceExport\Infrastructure\Persistence\Doctrine\Mapper\MaintenanceExportMapper;

use function array_map;
use function hash_equals;

/**
 * Class MaintenanceExportRepository
 *
 * Owns organization-scoped main persistence. Saved artifacts and replay receipts are append-only.
 *
 * @category Repository
 */
final readonly class MaintenanceExportRepository implements MaintenanceExportRepositoryPort
{
  // #region Properties
  /**
   * Property mapper
   *
   * Keeps exact storage encoding and integrity verification in one stateless mapper.
   */
  private MaintenanceExportMapper $mapper;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param Connection $connection explicitly configured main connection
   *
   * @return void
   */
  public function __construct(private Connection $connection)
  {
    $this->mapper = new MaintenanceExportMapper();
  }
  // #endregion

  // #region Methods
  /**
   * Method synchronized
   *
   * Source capture, document insertion and replay receipts share one transaction and organization lock.
   *
   * @access public
   *
   * @template T
   *
   * @param string $organizationId organization owning the exported sources
   * @param callable():T $work capture and persistence operation
   *
   * @return T committed result
   */
  public function synchronized(string $organizationId, callable $work): mixed
  {
    return $this->connection->transactional(function () use ($organizationId, $work): mixed {
      $this->lock($organizationId);

      return $work();
    });
  }

  /**
   * Method document
   *
   * @param string $organizationId owning organization
   * @param string $id document identity
   *
   * @return ExportDocument|null scoped preserved artifact
   */
  public function document(string $organizationId, string $id): ?ExportDocument
  {
    $row = $this->connection->fetchAssociative('SELECT * FROM maintenance_export_documents WHERE organization_id = :org AND id = :id', ['org' => $organizationId, 'id' => $id]);

    return false === $row ? null : $this->mapper->documentRow($row);
  }

  /**
   * Method saveDocument
   *
   * @param ExportDocument $document immutable artifact with optional new confirmation
   *
   * @return void updates only confirmation metadata for an existing document
   */
  public function saveDocument(ExportDocument $document): void
  {
    $this->lock($document->organizationId);
    $existing = $this->document($document->organizationId, $document->id);
    $immutable = $this->mapper->immutableData($document);
    if (null === $existing) {
      try {
        $this->connection->insert('maintenance_export_documents', $immutable + ['immutable_hash' => $this->mapper->immutableHash($document), 'revision' => $document->revision(), 'confirmation' => null === $document->confirmation() ? null : $this->mapper->json($document->confirmation())], ['include_internal_costs' => ParameterType::BOOLEAN, 'costs_complete' => null === $document->costsComplete ? ParameterType::NULL : ParameterType::BOOLEAN]);
      } catch (UniqueConstraintViolationException) {
        throw MaintenanceExportException::conflict('The export identity or preceding adjustment is already retained.');
      }

      return;
    }
    if (!hash_equals($this->mapper->immutableHash($existing), $this->mapper->immutableHash($document))) {
      throw MaintenanceExportException::conflict('Preserved export facts and artifacts cannot be changed.');
    }
    if ($existing->revision() === $document->revision() && $this->mapper->json($existing->confirmation()) === $this->mapper->json($document->confirmation())) {
      return;
    }
    if (null !== $existing->confirmation()) {
      throw MaintenanceExportException::conflict('An import acknowledgement cannot be replaced.');
    }
    if ($document->revision() !== $existing->revision() + 1 || null === $document->confirmation()) {
      throw MaintenanceExportException::stale();
    }
    $updated = $this->connection->executeStatement('UPDATE maintenance_export_documents SET revision = :revision, confirmation = CAST(:confirmation AS JSONB) WHERE organization_id = :org AND id = :id AND revision = :expected AND confirmation IS NULL', ['revision' => $document->revision(), 'confirmation' => $this->mapper->json($document->confirmation()), 'org' => $document->organizationId, 'id' => $document->id, 'expected' => $existing->revision()], ['revision' => ParameterType::INTEGER, 'expected' => ParameterType::INTEGER]);
    if (1 !== $updated) {
      throw MaintenanceExportException::stale();
    }
  }

  /**
   * Method hasAdjustment
   *
   * @param string $organizationId owning organization
   * @param string $id preceding export
   *
   * @return bool one retained direct adjustment exists
   */
  public function hasAdjustment(string $organizationId, string $id): bool
  {
    return $this->mapper->number($this->connection->fetchOne('SELECT COUNT(*) FROM maintenance_export_documents WHERE organization_id = :org AND adjustment_of = :id', ['org' => $organizationId, 'id' => $id])) > 0;
  }

  /**
   * Method documentSummaries
   *
   * @param string $organizationId owning organization
   * @param bool $includeFinancial financial read permission
   * @param string|null $system explicit ERP filter
   * @param int $offset server offset
   * @param int $limit bounded page size
   *
   * @return list<ExportDocumentSummary> metadata page without artifact or source-fact hydration
   */
  public function documentSummaries(string $organizationId, bool $includeFinancial, ?string $system, int $offset, int $limit): array
  {
    [$where, $params] = $this->documentSelection($organizationId, $includeFinancial, $system);
    $rows = $this->connection->fetchAllAssociative('SELECT id, organization_id, actor_id, kind, system, include_internal_costs, source_intervention_ids, original_export_id, adjustment_of, reason, created_at, revision, confirmation, costs_complete, incomplete_cost_count, json_sha256, csv_sha256, jsonb_array_length(rows) AS row_count, octet_length(json_bytes) AS json_size, octet_length(csv_bytes) AS csv_size FROM maintenance_export_documents WHERE ' . $where . ' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset', $params + ['limit' => $limit, 'offset' => $offset], ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER]);

    return array_map($this->mapper->documentSummaryRow(...), $rows);
  }

  /**
   * Method countDocuments
   *
   * @param string $organizationId owning organization
   * @param bool $includeFinancial financial read permission
   * @param string|null $system explicit ERP filter
   *
   * @return int same selection total
   */
  public function countDocuments(string $organizationId, bool $includeFinancial, ?string $system): int
  {
    [$where, $params] = $this->documentSelection($organizationId, $includeFinancial, $system);

    return $this->mapper->number($this->connection->fetchOne('SELECT COUNT(*) FROM maintenance_export_documents WHERE ' . $where, $params));
  }

  /**
   * Method operation
   *
   * @param string $organizationId owning organization
   * @param string $actorId signed actor
   * @param string $clientOperationId stable replay key
   *
   * @return ExportOperation|null original response receipt
   */
  public function operation(string $organizationId, string $actorId, string $clientOperationId): ?ExportOperation
  {
    $row = $this->connection->fetchAssociative('SELECT * FROM maintenance_export_operations WHERE organization_id = :org AND actor_id = :actor AND client_operation_id = :operation', ['org' => $organizationId, 'actor' => $actorId, 'operation' => $clientOperationId]);

    return false === $row ? null : $this->mapper->operationRow($row);
  }

  /**
   * Method saveOperation
   *
   * @param ExportOperation $operation immutable accepted response
   *
   * @return void retains one receipt per organization, actor and client operation
   */
  public function saveOperation(ExportOperation $operation): void
  {
    $this->lock($operation->organizationId);
    $existing = $this->operation($operation->organizationId, $operation->actorId, $operation->clientOperationId);
    if (null !== $existing) {
      if ($existing->action === $operation->action && $existing->fingerprint === $operation->fingerprint && $existing->resourceId === $operation->resourceId && $this->mapper->json($existing->result) === $this->mapper->json($operation->result)) {
        return;
      }

      throw MaintenanceExportException::conflict('The operation key belongs to a different retained declaration.');
    }

    try {
      $this->connection->insert('maintenance_export_operations', ['organization_id' => $operation->organizationId, 'actor_id' => $operation->actorId, 'client_operation_id' => $operation->clientOperationId, 'action' => $operation->action, 'fingerprint' => $operation->fingerprint, 'resource_id' => $operation->resourceId, 'result' => $this->mapper->json($operation->result)]);
    } catch (UniqueConstraintViolationException) {
      throw MaintenanceExportException::conflict('The operation receipt already exists.');
    }
  }

  /**
   * Method reference
   *
   * @param string $organizationId owning organization
   * @param string $system ERP system
   * @param string $resourceType published resource kind
   * @param string $resourceId organization-owned identity
   *
   * @return ExternalReference|null exact mapping
   */
  public function reference(string $organizationId, string $system, string $resourceType, string $resourceId): ?ExternalReference
  {
    $row = $this->connection->fetchAssociative('SELECT * FROM maintenance_external_references WHERE organization_id = :org AND system = :system AND resource_type = :type AND resource_id = :resource', ['org' => $organizationId, 'system' => $system, 'type' => $resourceType, 'resource' => $resourceId]);

    return false === $row ? null : $this->mapper->referenceRow($row);
  }

  /**
   * Method saveReference
   *
   * @param ExternalReference $reference exact target with next revision
   *
   * @return void persists optimistic reference changes without touching documents
   */
  public function saveReference(ExternalReference $reference): void
  {
    $this->lock($reference->organizationId);
    $existing = $this->reference($reference->organizationId, $reference->system, $reference->resourceType, $reference->resourceId);
    if (null === $existing) {
      if (1 !== $reference->revision) {
        throw MaintenanceExportException::stale();
      }

      try {
        $this->connection->insert('maintenance_external_references', ['id' => $reference->id, 'organization_id' => $reference->organizationId, 'system' => $reference->system, 'resource_type' => $reference->resourceType, 'resource_id' => $reference->resourceId, 'reference' => $reference->reference, 'revision' => $reference->revision, 'updated_at' => $this->mapper->time($reference->updatedAt)]);
      } catch (UniqueConstraintViolationException) {
        throw MaintenanceExportException::conflict('The external reference identity already exists.');
      }

      return;
    }
    if ($existing->id !== $reference->id) {
      throw MaintenanceExportException::conflict('An external reference keeps its original identity.');
    }
    if ($existing->revision === $reference->revision && $existing->reference === $reference->reference && $this->mapper->time($existing->updatedAt) === $this->mapper->time($reference->updatedAt)) {
      return;
    }
    if ($reference->revision !== $existing->revision + 1) {
      throw MaintenanceExportException::stale();
    }
    $updated = $this->connection->executeStatement('UPDATE maintenance_external_references SET reference = :reference, revision = :revision, updated_at = :at WHERE organization_id = :org AND id = :id AND revision = :expected', ['reference' => $reference->reference, 'revision' => $reference->revision, 'at' => $this->mapper->time($reference->updatedAt), 'org' => $reference->organizationId, 'id' => $reference->id, 'expected' => $existing->revision], ['revision' => ParameterType::INTEGER, 'expected' => ParameterType::INTEGER]);
    if (1 !== $updated) {
      throw MaintenanceExportException::stale();
    }
  }

  /**
   * Method references
   *
   * @param string $organizationId owning organization
   * @param string|null $system ERP filter
   * @param string|null $resourceType resource kind filter
   * @param string|null $resourceId resource identity filter
   * @param int $offset server offset
   * @param int $limit bounded page size
   *
   * @return list<ExternalReference> same-scope mapping page
   */
  public function references(string $organizationId, ?string $system, ?string $resourceType, ?string $resourceId, int $offset, int $limit): array
  {
    [$where, $params] = $this->referenceSelection($organizationId, $system, $resourceType, $resourceId);
    $rows = $this->connection->fetchAllAssociative('SELECT * FROM maintenance_external_references WHERE ' . $where . ' ORDER BY system, resource_type, resource_id, id LIMIT :limit OFFSET :offset', $params + ['limit' => $limit, 'offset' => $offset], ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER]);

    return array_map($this->mapper->referenceRow(...), $rows);
  }

  /**
   * Method countReferences
   *
   * @param string $organizationId owning organization
   * @param string|null $system ERP filter
   * @param string|null $resourceType resource kind filter
   * @param string|null $resourceId resource identity filter
   *
   * @return int same selection total
   */
  public function countReferences(string $organizationId, ?string $system, ?string $resourceType, ?string $resourceId): int
  {
    [$where, $params] = $this->referenceSelection($organizationId, $system, $resourceType, $resourceId);

    return $this->mapper->number($this->connection->fetchOne('SELECT COUNT(*) FROM maintenance_external_references WHERE ' . $where, $params));
  }

  /**
   * Method lock
   *
   * @param string $organizationId organization owning all transaction writes
   *
   * @return void requires an ambient main transaction
   */
  private function lock(string $organizationId): void
  {
    if (!$this->connection->isTransactionActive()) {
      throw new LogicException('Maintenance export writes require a main transaction.');
    }
    $this->connection->executeQuery('SELECT pg_advisory_xact_lock(hashtextextended(:key, 0))', ['key' => 'maintenance_export.' . $organizationId]);
  }

  /**
   * Method documentSelection
   *
   * @param string $org owning organization
   * @param bool $financial applicable financial permission
   * @param string|null $system optional ERP filter
   *
   * @return array{string,array<string,string>} bound document predicate
   */
  private function documentSelection(string $org, bool $financial, ?string $system): array
  {
    $where = 'organization_id = :org' . ($financial ? '' : ' AND include_internal_costs = FALSE');
    $params = ['org' => $org];
    if (null !== $system) {
      $where .= ' AND system = :system';
      $params['system'] = $system;
    }

    return [$where, $params];
  }

  /**
   * Method referenceSelection
   *
   * @param string $org owning organization
   * @param string|null $system ERP filter
   * @param string|null $type resource kind filter
   * @param string|null $resource resource identity filter
   *
   * @return array{string,array<string,string>} exact bound mapping predicate
   */
  private function referenceSelection(string $org, ?string $system, ?string $type, ?string $resource): array
  {
    $where = 'organization_id = :org';
    $params = ['org' => $org];
    foreach (['system' => $system, 'resource_type' => $type, 'resource_id' => $resource] as $key => $value) {
      if (null !== $value) {
        $where .= ' AND ' . $key . ' = :' . $key;
        $params[$key] = $value;
      }
    }

    return [$where, $params];
  }

  // #endregion
}
