<?php

declare(strict_types=1);

namespace MaintenanceExport\Application\Port\Outbound;

use MaintenanceExport\Application\Contract\{ExportDocumentSummary, ExportOperation};
use MaintenanceExport\Domain\Model\{ExportDocument, ExternalReference};

/**
 * Interface MaintenanceExportRepositoryPort
 * One explicit main transaction serializes source capture, references and operation receipts.
 *
 * @category Port
 */
interface MaintenanceExportRepositoryPort
{
  // #region Methods
  /**
   * Method synchronized
   *
   * @template T
   *
   * @param callable():T $work main-transaction work
   *
   * @return T durable result
   */
  public function synchronized(string $organizationId, callable $work): mixed;

  /**
   * Method document
   *
   * @return ExportDocument|null same-organization immutable artifact
   */
  public function document(string $organizationId, string $id): ?ExportDocument;

  /**
   * Method saveDocument
   *
   * @return void inserts new immutable artifacts, updates existing confirmation only
   */
  public function saveDocument(ExportDocument $document): void;

  /**
   * Method hasAdjustment
   *
   * @return bool preceding document already has a child adjustment
   */
  public function hasAdjustment(string $organizationId, string $id): bool;

  /**
   * Method documentSummaries
   *
   * Reads only retained metadata, counts and fingerprints without loading artifact bytes or source facts.
   *
   * @param bool $includeFinancial caller possesses financial read permission
   *
   * @return list<ExportDocumentSummary> ordered metadata page
   */
  public function documentSummaries(string $organizationId, bool $includeFinancial, ?string $system, int $offset, int $limit): array;

  /**
   * Method countDocuments
   *
   * @return int visible server page total
   */
  public function countDocuments(string $organizationId, bool $includeFinancial, ?string $system): int;

  /**
   * Method operation
   *
   * @return ExportOperation|null original actor-scoped receipt
   */
  public function operation(string $organizationId, string $actorId, string $clientOperationId): ?ExportOperation;

  /**
   * Method saveOperation
   *
   * @return void unique canonical operation receipt
   */
  public function saveOperation(ExportOperation $operation): void;

  /**
   * Method reference
   *
   * @return ExternalReference|null exact same-organization system mapping
   */
  public function reference(string $organizationId, string $system, string $resourceType, string $resourceId): ?ExternalReference;

  /**
   * Method saveReference
   *
   * @return void optimistic changes within owning transaction
   */
  public function saveReference(ExternalReference $reference): void;

  /**
   * Method references
   *
   * @return list<ExternalReference> bounded reference directory
   */
  public function references(string $organizationId, ?string $system, ?string $resourceType, ?string $resourceId, int $offset, int $limit): array;

  /**
   * Method countReferences
   *
   * @return int filtered directory total
   */
  public function countReferences(string $organizationId, ?string $system, ?string $resourceType, ?string $resourceId): int;
  // #endregion
}
