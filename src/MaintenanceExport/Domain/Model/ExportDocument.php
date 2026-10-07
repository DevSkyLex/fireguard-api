<?php

declare(strict_types=1);

namespace MaintenanceExport\Domain\Model;

use DateTimeImmutable;
use MaintenanceExport\Domain\Exception\MaintenanceExportException;
use MaintenanceExport\Domain\ValueObject\{ExportArtifact, ExportIdentity};

use function count;
use function hash;
use function in_array;
use function strlen;

/**
 * Class ExportDocument
 * Preserved artifacts never change when an import or later adjustment is recorded.
 *
 * @category Model
 */
final class ExportDocument
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Rehydrates retained artifacts. Only confirmation and its revision can subsequently change.
   *
   * @param list<string> $sourceInterventionIds published source identifiers
   * @param list<array<string,mixed>> $rows immutable exported row set
   * @param array<string,array<string,mixed>> $baseline current fact state at generation, used only to calculate later deltas
   * @param array<string,mixed>|null $confirmation explicit ERP receipt, independent from generation
   */
  public function __construct(
    public readonly string $id,
    public readonly string $organizationId,
    public readonly string $actorId,
    public readonly string $kind,
    public readonly string $system,
    public readonly bool $includeInternalCosts,
    public readonly array $sourceInterventionIds,
    public readonly ?string $originalExportId,
    public readonly ?string $adjustmentOf,
    public readonly ?string $reason,
    public readonly DateTimeImmutable $createdAt,
    public readonly array $rows,
    public readonly array $baseline,
    public readonly string $jsonBytes,
    public readonly string $csvBytes,
    public readonly ?bool $costsComplete = null,
    public readonly ?int $incompleteCostCount = null,
    private int $revision = 1,
    private ?array $confirmation = null,
  ) {
    ExportIdentity::uuid($id);
    ExportIdentity::uuid($organizationId);
    ExportIdentity::uuid($actorId);
    ExportIdentity::system($system);
    if (!in_array($kind, ['initial', 'adjustment'], true) || count($rows) > ExportArtifact::MAX_ROWS || $revision < 1 || strlen($jsonBytes) > ExportArtifact::MAX_BYTES || strlen($csvBytes) > ExportArtifact::MAX_BYTES) {
      throw MaintenanceExportException::invalid('Invalid retained export.');
    }
    if ('adjustment' === $kind && (null === $originalExportId || null === $adjustmentOf || null === $reason)) {
      throw MaintenanceExportException::invalid('An adjustment must retain its original and preceding export.');
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method revision
   *
   * @return int confirmation revision
   */
  public function revision(): int
  {
    return $this->revision;
  }

  /**
   * Method confirmation
   *
   * @return array<string,mixed>|null explicit external import receipt
   */
  public function confirmation(): ?array
  {
    return $this->confirmation;
  }

  /**
   * Method assertRevision
   *
   * @param int|null $expected If-Match revision
   *
   * @return void
   */
  public function assertRevision(?int $expected): void
  {
    if (null === $expected) {
      throw MaintenanceExportException::revisionRequired();
    }
    if ($expected !== $this->revision) {
      throw MaintenanceExportException::stale();
    }
  }

  /**
   * Method confirm
   *
   * @return void records import acknowledgement without changing any artifact
   */
  public function confirm(int $expected, string $clientOperationId, string $reference, string $actorId, DateTimeImmutable $at): void
  {
    $this->assertRevision($expected);
    if (null !== $this->confirmation) {
      throw MaintenanceExportException::conflict('The export already has an import acknowledgement.');
    }
    $this->confirmation = ['clientOperationId' => ExportIdentity::uuid($clientOperationId), 'externalImportReference' => ExportIdentity::text($reference, 200), 'actorId' => ExportIdentity::uuid($actorId), 'confirmedAt' => $at->format('c')];
    ++$this->revision;
  }

  /**
   * Method projection
   *
   * @return array<string,mixed> public metadata, artifacts obtained only through an authorized download
   */
  public function projection(): array
  {
    return ['id' => $this->id, 'organizationId' => $this->organizationId, 'actorId' => $this->actorId, 'kind' => $this->kind, 'schemaVersion' => 1, 'system' => $this->system, 'includeInternalCosts' => $this->includeInternalCosts, 'sourceInterventionIds' => $this->sourceInterventionIds, 'originalExportId' => $this->originalExportId, 'adjustmentOf' => $this->adjustmentOf, 'reason' => $this->reason, 'createdAt' => $this->createdAt->format('c'), 'revision' => $this->revision, 'state' => null === $this->confirmation ? 'generated' : 'import_confirmed', 'confirmation' => $this->confirmation, 'rowCount' => count($this->rows), 'costsComplete' => $this->costsComplete, 'incompleteCostCount' => $this->incompleteCostCount, 'files' => ['json' => ['mediaType' => 'application/json', 'sha256' => hash('sha256', $this->jsonBytes), 'size' => strlen($this->jsonBytes)], 'csv' => ['mediaType' => 'text/csv', 'sha256' => hash('sha256', $this->csvBytes), 'size' => strlen($this->csvBytes)]]];
  }
  // #endregion
}
