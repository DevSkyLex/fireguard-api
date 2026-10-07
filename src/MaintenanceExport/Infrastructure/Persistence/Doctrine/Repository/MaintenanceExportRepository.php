<?php

declare(strict_types=1);

namespace MaintenanceExport\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\{Connection, Exception\UniqueConstraintViolationException, ParameterType};
use LogicException;
use MaintenanceExport\Application\Contract\{ExportDocumentSummary, ExportOperation};
use MaintenanceExport\Application\Port\Outbound\MaintenanceExportRepositoryPort;
use MaintenanceExport\Domain\Exception\MaintenanceExportException;
use MaintenanceExport\Domain\Model\{ExportDocument, ExternalReference};

use function array_is_list;
use function array_map;
use function hash;
use function hash_equals;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function ksort;
use function preg_match;

use const JSON_THROW_ON_ERROR;
use const SORT_STRING;

/**
 * Class MaintenanceExportRepository
 *
 * Owns organization-scoped main persistence. Saved artifacts and replay receipts are append-only.
 *
 * @category Repository
 */
final readonly class MaintenanceExportRepository implements MaintenanceExportRepositoryPort
{
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

    return false === $row ? null : $this->documentRow($row);
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
    $immutable = $this->immutableData($document);
    if (null === $existing) {
      try {
        $this->connection->insert('maintenance_export_documents', $immutable + ['immutable_hash' => $this->immutableHash($document), 'revision' => $document->revision(), 'confirmation' => null === $document->confirmation() ? null : $this->json($document->confirmation())], ['include_internal_costs' => ParameterType::BOOLEAN, 'costs_complete' => null === $document->costsComplete ? ParameterType::NULL : ParameterType::BOOLEAN]);
      } catch (UniqueConstraintViolationException) {
        throw MaintenanceExportException::conflict('The export identity or preceding adjustment is already retained.');
      }

      return;
    }
    if (!hash_equals($this->immutableHash($existing), $this->immutableHash($document))) {
      throw MaintenanceExportException::conflict('Preserved export facts and artifacts cannot be changed.');
    }
    if ($existing->revision() === $document->revision() && $this->json($existing->confirmation()) === $this->json($document->confirmation())) {
      return;
    }
    if (null !== $existing->confirmation()) {
      throw MaintenanceExportException::conflict('An import acknowledgement cannot be replaced.');
    }
    if ($document->revision() !== $existing->revision() + 1 || null === $document->confirmation()) {
      throw MaintenanceExportException::stale();
    }
    $updated = $this->connection->executeStatement('UPDATE maintenance_export_documents SET revision = :revision, confirmation = CAST(:confirmation AS JSONB) WHERE organization_id = :org AND id = :id AND revision = :expected AND confirmation IS NULL', ['revision' => $document->revision(), 'confirmation' => $this->json($document->confirmation()), 'org' => $document->organizationId, 'id' => $document->id, 'expected' => $existing->revision()], ['revision' => ParameterType::INTEGER, 'expected' => ParameterType::INTEGER]);
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
    return $this->number($this->connection->fetchOne('SELECT COUNT(*) FROM maintenance_export_documents WHERE organization_id = :org AND adjustment_of = :id', ['org' => $organizationId, 'id' => $id])) > 0;
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

    return array_map($this->documentSummaryRow(...), $rows);
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

    return $this->number($this->connection->fetchOne('SELECT COUNT(*) FROM maintenance_export_documents WHERE ' . $where, $params));
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

    return false === $row ? null : new ExportOperation($this->string($row, 'organization_id'), $this->string($row, 'actor_id'), $this->string($row, 'client_operation_id'), $this->string($row, 'action'), $this->string($row, 'fingerprint'), $this->string($row, 'resource_id'), $this->object($this->string($row, 'result')));
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
      if ($existing->action === $operation->action && $existing->fingerprint === $operation->fingerprint && $existing->resourceId === $operation->resourceId && $this->json($existing->result) === $this->json($operation->result)) {
        return;
      }

      throw MaintenanceExportException::conflict('The operation key belongs to a different retained declaration.');
    }

    try {
      $this->connection->insert('maintenance_export_operations', ['organization_id' => $operation->organizationId, 'actor_id' => $operation->actorId, 'client_operation_id' => $operation->clientOperationId, 'action' => $operation->action, 'fingerprint' => $operation->fingerprint, 'resource_id' => $operation->resourceId, 'result' => $this->json($operation->result)]);
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

    return false === $row ? null : $this->referenceRow($row);
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
        $this->connection->insert('maintenance_external_references', ['id' => $reference->id, 'organization_id' => $reference->organizationId, 'system' => $reference->system, 'resource_type' => $reference->resourceType, 'resource_id' => $reference->resourceId, 'reference' => $reference->reference, 'revision' => $reference->revision, 'updated_at' => $this->time($reference->updatedAt)]);
      } catch (UniqueConstraintViolationException) {
        throw MaintenanceExportException::conflict('The external reference identity already exists.');
      }

      return;
    }
    if ($existing->id !== $reference->id) {
      throw MaintenanceExportException::conflict('An external reference keeps its original identity.');
    }
    if ($existing->revision === $reference->revision && $existing->reference === $reference->reference && $this->time($existing->updatedAt) === $this->time($reference->updatedAt)) {
      return;
    }
    if ($reference->revision !== $existing->revision + 1) {
      throw MaintenanceExportException::stale();
    }
    $updated = $this->connection->executeStatement('UPDATE maintenance_external_references SET reference = :reference, revision = :revision, updated_at = :at WHERE organization_id = :org AND id = :id AND revision = :expected', ['reference' => $reference->reference, 'revision' => $reference->revision, 'at' => $this->time($reference->updatedAt), 'org' => $reference->organizationId, 'id' => $reference->id, 'expected' => $existing->revision], ['revision' => ParameterType::INTEGER, 'expected' => ParameterType::INTEGER]);
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

    return array_map($this->referenceRow(...), $rows);
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

    return $this->number($this->connection->fetchOne('SELECT COUNT(*) FROM maintenance_external_references WHERE ' . $where, $params));
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
   * Method immutableData
   *
   * @param ExportDocument $document preserved document
   *
   * @return array<string,mixed> immutable SQL values only
   */
  private function immutableData(ExportDocument $document): array
  {
    return ['id' => $document->id, 'organization_id' => $document->organizationId, 'actor_id' => $document->actorId, 'kind' => $document->kind, 'system' => $document->system, 'include_internal_costs' => $document->includeInternalCosts, 'source_intervention_ids' => $this->json($document->sourceInterventionIds), 'original_export_id' => $document->originalExportId, 'adjustment_of' => $document->adjustmentOf, 'reason' => $document->reason, 'created_at' => $this->time($document->createdAt), 'rows' => $this->json($document->rows), 'baseline' => $this->json($document->baseline), 'json_bytes' => $document->jsonBytes, 'csv_bytes' => $document->csvBytes, 'json_sha256' => hash('sha256', $document->jsonBytes), 'csv_sha256' => hash('sha256', $document->csvBytes), 'costs_complete' => $document->costsComplete, 'incomplete_cost_count' => $document->incompleteCostCount];
  }

  /**
   * Method immutableHash
   *
   * @param ExportDocument $document preserved document
   *
   * @return string canonical fingerprint unaffected by JSONB object-key ordering
   */
  private function immutableHash(ExportDocument $document): string
  {
    return hash('sha256', $this->json($this->immutableData($document)));
  }

  /**
   * Method documentRow
   *
   * @param array<string,mixed> $row same-organization SQL row
   *
   * @return ExportDocument preserved artifact after integrity verification
   */
  private function documentRow(array $row): ExportDocument
  {
    $sources = $this->sourceIdentities($row);
    $rows = json_decode($this->string($row, 'rows'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($rows) || !array_is_list($rows)) {
      throw new LogicException('Invalid retained export row set.');
    }
    $exportedRows = [];
    foreach ($rows as $exportedRow) {
      $exportedRows[] = $this->stringKeys($exportedRow);
    }
    $baseline = [];
    foreach ($this->object($this->string($row, 'baseline')) as $key => $value) {
      $baseline[$key] = $this->stringKeys($value);
    }
    $document = new ExportDocument($this->string($row, 'id'), $this->string($row, 'organization_id'), $this->string($row, 'actor_id'), $this->string($row, 'kind'), $this->string($row, 'system'), $this->boolean($row['include_internal_costs'] ?? null), $sources, $this->nullableString($row, 'original_export_id'), $this->nullableString($row, 'adjustment_of'), $this->nullableString($row, 'reason'), new DateTimeImmutable($this->string($row, 'created_at'), new DateTimeZone('UTC')), $exportedRows, $baseline, $this->string($row, 'json_bytes'), $this->string($row, 'csv_bytes'), null === ($row['costs_complete'] ?? null) ? null : $this->boolean($row['costs_complete']), null === ($row['incomplete_cost_count'] ?? null) ? null : $this->number($row['incomplete_cost_count']), $this->number($row['revision'] ?? null), null === ($row['confirmation'] ?? null) ? null : $this->object($this->string($row, 'confirmation')));
    if (!hash_equals($this->string($row, 'json_sha256'), hash('sha256', $document->jsonBytes)) || !hash_equals($this->string($row, 'csv_sha256'), hash('sha256', $document->csvBytes)) || !hash_equals($this->string($row, 'immutable_hash'), $this->immutableHash($document))) {
      throw new LogicException('Retained export integrity verification failed.');
    }

    return $document;
  }

  /**
   * Method documentSummaryRow
   *
   * @access private
   *
   * @param array<string,mixed> $row projected organization-scoped metadata
   *
   * @return ExportDocumentSummary saved counts and hashes without rehydrating an artifact
   */
  private function documentSummaryRow(array $row): ExportDocumentSummary
  {
    return new ExportDocumentSummary($this->string($row, 'id'), $this->string($row, 'organization_id'), $this->string($row, 'actor_id'), $this->string($row, 'kind'), $this->string($row, 'system'), $this->boolean($row['include_internal_costs'] ?? null), $this->sourceIdentities($row), $this->nullableString($row, 'original_export_id'), $this->nullableString($row, 'adjustment_of'), $this->nullableString($row, 'reason'), new DateTimeImmutable($this->string($row, 'created_at'), new DateTimeZone('UTC')), $this->number($row['revision'] ?? null), null === ($row['confirmation'] ?? null) ? null : $this->object($this->string($row, 'confirmation')), $this->number($row['row_count'] ?? null), null === ($row['costs_complete'] ?? null) ? null : $this->boolean($row['costs_complete']), null === ($row['incomplete_cost_count'] ?? null) ? null : $this->number($row['incomplete_cost_count']), $this->string($row, 'json_sha256'), $this->number($row['json_size'] ?? null), $this->string($row, 'csv_sha256'), $this->number($row['csv_size'] ?? null));
  }

  /**
   * Method sourceIdentities
   *
   * @access private
   *
   * @param array<string,mixed> $row retained source-selection metadata
   *
   * @return list<string> ordered published source identities
   */
  private function sourceIdentities(array $row): array
  {
    $sourceIds = json_decode($this->string($row, 'source_intervention_ids'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($sourceIds) || !array_is_list($sourceIds)) {
      throw new LogicException('Invalid retained export source identities.');
    }
    $sources = [];
    foreach ($sourceIds as $sourceId) {
      if (!is_string($sourceId)) {
        throw new LogicException('Invalid retained export source identity.');
      }
      $sources[] = $sourceId;
    }

    return $sources;
  }

  /**
   * Method referenceRow
   *
   * @param array<string,mixed> $row scoped SQL mapping
   *
   * @return ExternalReference exact optimistic mapping
   */
  private function referenceRow(array $row): ExternalReference
  {
    return new ExternalReference($this->string($row, 'id'), $this->string($row, 'organization_id'), $this->string($row, 'system'), $this->string($row, 'resource_type'), $this->string($row, 'resource_id'), $this->string($row, 'reference'), $this->number($row['revision'] ?? null), new DateTimeImmutable($this->string($row, 'updated_at'), new DateTimeZone('UTC')));
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

  /**
   * Method time
   *
   * @param DateTimeImmutable $date source instant
   *
   * @return string UTC storage instant
   */
  private function time(DateTimeImmutable $date): string
  {
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
  }

  /**
   * Method json
   *
   * @param mixed $value preserved JSON value
   *
   * @return string canonical object ordering with exact list ordering
   */
  private function json(mixed $value): string
  {
    return json_encode($this->canonical($value), JSON_THROW_ON_ERROR);
  }

  /**
   * Method canonical
   *
   * @param mixed $value JSON-compatible value
   *
   * @return mixed recursively stable object ordering
   */
  private function canonical(mixed $value): mixed
  {
    if (!is_array($value)) {
      return $value;
    }
    if (!array_is_list($value)) {
      ksort($value, SORT_STRING);
    }
    foreach ($value as $key => $child) {
      $value[$key] = $this->canonical($child);
    }

    return $value;
  }

  /**
   * Method object
   *
   * @param string $json stored JSON mapping
   *
   * @return array<string,mixed> decoded string-keyed mapping
   */
  private function object(string $json): array
  {
    return $this->stringKeys(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
  }

  /**
   * Method stringKeys
   *
   * @param mixed $value stored mapping candidate
   *
   * @return array<string,mixed> validated string keys
   */
  private function stringKeys(mixed $value): array
  {
    if (!is_array($value)) {
      throw new LogicException('Invalid retained export JSON mapping.');
    }
    $result = [];
    foreach ($value as $key => $child) {
      if (!is_string($key)) {
        throw new LogicException('Invalid retained export JSON mapping key.');
      }
      $result[$key] = $child;
    }

    return $result;
  }

  /**
   * Method string
   *
   * @param array<string,mixed> $row SQL row
   * @param string $key expected text column
   *
   * @return string validated stored text
   */
  private function string(array $row, string $key): string
  {
    $value = $row[$key] ?? null;
    if (!is_string($value)) {
      throw new LogicException('Invalid retained export text field ' . $key . '.');
    }

    return $value;
  }

  /**
   * Method nullableString
   *
   * @param array<string,mixed> $row SQL row
   * @param string $key nullable text column
   *
   * @return string|null validated nullable text
   */
  private function nullableString(array $row, string $key): ?string
  {
    return null === ($row[$key] ?? null) ? null : $this->string($row, $key);
  }

  /**
   * Method number
   *
   * @param mixed $value integer column or aggregate
   *
   * @return int validated nonnegative integer
   */
  private function number(mixed $value): int
  {
    if (is_int($value)) {
      return $value;
    }
    if (is_string($value) && 1 === preg_match('/^\d+$/D', $value)) {
      return (int) $value;
    }

    throw new LogicException('Invalid retained export integer field.');
  }

  /**
   * Method boolean
   *
   * @param mixed $value PostgreSQL boolean field
   *
   * @return bool validated driver boolean representation
   */
  private function boolean(mixed $value): bool
  {
    if (is_bool($value)) {
      return $value;
    }

    return match ($value) {
      1, '1', 't', 'true' => true,
      0, '0', 'f', 'false' => false,
      default => throw new LogicException('Invalid retained export boolean field.'),
    };
  }
  // #endregion
}
