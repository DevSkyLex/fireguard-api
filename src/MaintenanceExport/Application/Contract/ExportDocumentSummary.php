<?php

declare(strict_types=1);

namespace MaintenanceExport\Application\Contract;

use DateTimeImmutable;

/**
 * Class ExportDocumentSummary
 *
 * Represents retained archive metadata without rehydrating artifact bytes or correction facts.
 * Hashes and byte counts describe the saved files; full reads verify their integrity separately.
 *
 * @category Contract
 */
final readonly class ExportDocumentSummary
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $id retained document identity
   * @param string $organizationId owning organization
   * @param string $actorId generating actor
   * @param string $kind initial or adjustment export
   * @param string $system external ERP system
   * @param bool $includeInternalCosts independently protected financial visibility
   * @param list<string> $sourceInterventionIds retained source selection
   * @param string|null $originalExportId adjustment chain origin
   * @param string|null $adjustmentOf preceding retained artifact
   * @param string|null $reason adjustment motivation
   * @param DateTimeImmutable $createdAt generation instant
   * @param int $revision confirmation revision
   * @param array<string,mixed>|null $confirmation explicit external import acknowledgement
   * @param int $rowCount saved output row count
   * @param bool|null $costsComplete financial completeness, absent for operational exports
   * @param int|null $incompleteCostCount unknown financial contribution count
   * @param string $jsonSha256 saved JSON fingerprint
   * @param int $jsonSize saved UTF-8 JSON byte count
   * @param string $csvSha256 saved CSV fingerprint
   * @param int $csvSize saved UTF-8 CSV byte count
   *
   * @return void
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $actorId,
    public string $kind,
    public string $system,
    public bool $includeInternalCosts,
    public array $sourceInterventionIds,
    public ?string $originalExportId,
    public ?string $adjustmentOf,
    public ?string $reason,
    public DateTimeImmutable $createdAt,
    public int $revision,
    public ?array $confirmation,
    public int $rowCount,
    public ?bool $costsComplete,
    public ?int $incompleteCostCount,
    public string $jsonSha256,
    public int $jsonSize,
    public string $csvSha256,
    public int $csvSize,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method projection
   *
   * @access public
   *
   * @return array<string,mixed> existing public metadata contract without file contents
   */
  public function projection(): array
  {
    return ['id' => $this->id, 'organizationId' => $this->organizationId, 'actorId' => $this->actorId, 'kind' => $this->kind, 'schemaVersion' => 1, 'system' => $this->system, 'includeInternalCosts' => $this->includeInternalCosts, 'sourceInterventionIds' => $this->sourceInterventionIds, 'originalExportId' => $this->originalExportId, 'adjustmentOf' => $this->adjustmentOf, 'reason' => $this->reason, 'createdAt' => $this->createdAt->format('c'), 'revision' => $this->revision, 'state' => null === $this->confirmation ? 'generated' : 'import_confirmed', 'confirmation' => $this->confirmation, 'rowCount' => $this->rowCount, 'costsComplete' => $this->costsComplete, 'incompleteCostCount' => $this->incompleteCostCount, 'files' => ['json' => ['mediaType' => 'application/json', 'sha256' => $this->jsonSha256, 'size' => $this->jsonSize], 'csv' => ['mediaType' => 'text/csv', 'sha256' => $this->csvSha256, 'size' => $this->csvSize]]];
  }
  // #endregion
}
