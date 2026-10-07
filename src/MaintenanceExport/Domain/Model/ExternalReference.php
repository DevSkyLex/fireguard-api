<?php

declare(strict_types=1);

namespace MaintenanceExport\Domain\Model;

use DateTimeImmutable;
use MaintenanceExport\Domain\Exception\MaintenanceExportException;
use MaintenanceExport\Domain\ValueObject\ExportIdentity;

/**
 * Class ExternalReference
 * Maps an organization-owned identity to an explicitly named ERP system.
 *
 * @category Model
 */
final readonly class ExternalReference
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @return void validates exact target and reference
   */
  public function __construct(public string $id, public string $organizationId, public string $system, public string $resourceType, public string $resourceId, public string $reference, public int $revision, public DateTimeImmutable $updatedAt)
  {
    ExportIdentity::uuid($id);
    ExportIdentity::uuid($organizationId);
    ExportIdentity::uuid($resourceId);
    ExportIdentity::system($system);
    ExportIdentity::resourceType($resourceType);
    ExportIdentity::text($reference, 200);
    if ($revision < 1) {
      throw MaintenanceExportException::invalid('Invalid reference revision.');
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method projection
   *
   * @return array<string,mixed> no contacts or financial data
   */
  public function projection(): array
  {
    return ['id' => $this->id, 'organizationId' => $this->organizationId, 'system' => $this->system, 'resourceType' => $this->resourceType, 'resourceId' => $this->resourceId, 'reference' => $this->reference, 'revision' => $this->revision, 'updatedAt' => $this->updatedAt->format('c')];
  }
  // #endregion
}
