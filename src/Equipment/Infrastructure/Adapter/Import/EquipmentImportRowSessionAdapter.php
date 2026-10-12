<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Import;

use Equipment\Application\Contract\Provisioning\{ProvisionEquipmentRequest, ProvisionOutcome};
use Equipment\Application\Port\Inbound\EquipmentProvisioningPort;
use Equipment\Infrastructure\Import\EquipmentImportRowFactory;
use Import\Application\Contract\{ImportRowInput, ImportRowOutcome, ImportSessionContext};
use Import\Application\Port\Outbound\ImportRowSessionPort;

/**
 * Class EquipmentImportRowSessionAdapter
 *
 * Keeps successful simulation offsets inside one equipment job execution.
 *
 * @category Adapter
 */
final class EquipmentImportRowSessionAdapter implements ImportRowSessionPort
{
  // #region Properties
  /**
   * Property projectedCount
   */
  private int $projectedCount = 0;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param ImportSessionContext $context immutable job context
   * @param EquipmentProvisioningPort $provisioning owner provisioning capability
   * @param EquipmentImportRowFactory $rows source-row structural validation
   *
   * @return void
   */
  public function __construct(
    private readonly ImportSessionContext $context,
    private readonly EquipmentProvisioningPort $provisioning,
    private readonly EquipmentImportRowFactory $rows = new EquipmentImportRowFactory(),
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method processRow
   *
   * @access public
   *
   * @param ImportRowInput $input decoded original CSV row
   *
   * @return ImportRowOutcome safe owner provisioning result
   */
  public function processRow(ImportRowInput $input): ImportRowOutcome
  {
    $request = $this->rows->map($this->context->organizationId, $input->row);
    if ($this->context->dryRun) {
      $request = new ProvisionEquipmentRequest(
        organizationId: $request->organizationId,
        type: $request->type,
        subType: $request->subType,
        brand: $request->brand,
        model: $request->model,
        serialNumber: $request->serialNumber,
        locationLabel: $request->locationLabel,
        facilityCode: $request->facilityCode,
        dryRun: true,
        quotaProjectionOffset: $this->projectedCount,
      );
    }
    $result = $this->provisioning->provision($request);
    if (ProvisionOutcome::CREATED === $result->outcome) {
      if ($this->context->dryRun) {
        ++$this->projectedCount;

        return new ImportRowOutcome(true, code: 'would_create', message: 'Would create this equipment item.');
      }

      return new ImportRowOutcome(true, $result->resourceId);
    }

    return match ($result->outcome) {
      ProvisionOutcome::QUOTA_EXCEEDED => new ImportRowOutcome(false, code: 'quota_exceeded', message: $result->message ?? 'The plan quota for equipment has been reached.'),
      ProvisionOutcome::INVALID => new ImportRowOutcome(false, code: 'invalid', message: $result->message ?? 'Invalid equipment row.'),
    };
  }

  /**
   * Method restoreConfirmedSimulationRow
   *
   * Counts a retained success without revalidating or consuming the owner quota.
   *
   * @access public
   *
   * @param ImportRowInput $input retained successful source row
   *
   * @return void
   */
  public function restoreConfirmedSimulationRow(ImportRowInput $input): void
  {
    ++$this->projectedCount;
  }
  // #endregion
}
