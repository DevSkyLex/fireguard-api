<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Import;

use Facility\Application\Contract\Provisioning\{ProvisionFacilityRequest, ProvisionOutcome};
use Facility\Application\Port\Inbound\FacilityProvisioningPort;
use Facility\Infrastructure\Import\{FacilityImportProjection, FacilityImportRowFactory};
use Import\Application\Contract\{ImportRowInput, ImportRowOutcome, ImportSessionContext};
use Import\Application\Port\Outbound\ImportRowSessionPort;
use LogicException;

/**
 * Class FacilityImportRowSessionAdapter
 *
 * Keeps quota offsets, preceding codes and typed hierarchy inside one job execution.
 *
 * @category Adapter
 */
final readonly class FacilityImportRowSessionAdapter implements ImportRowSessionPort
{
  // #region Properties
  /**
   * Property projection
   */
  private FacilityImportProjection $projection;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param ImportSessionContext $context immutable job context
   * @param FacilityProvisioningPort $provisioning owner provisioning capability
   * @param FacilityImportRowFactory $rows source-row structural validation
   *
   * @return void
   */
  public function __construct(
    private ImportSessionContext $context,
    private FacilityProvisioningPort $provisioning,
    private FacilityImportRowFactory $rows = new FacilityImportRowFactory(),
  ) {
    $this->projection = new FacilityImportProjection();
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
    $request = $this->context->dryRun ? $this->simulationRequest($input) : $this->rows->map($this->context->organizationId, $input->row);
    $result = $this->provisioning->provision($request);
    if (ProvisionOutcome::CREATED === $result->outcome) {
      if ($this->context->dryRun) {
        if (null === $result->projectedNode) {
          throw new LogicException('A successful facility simulation must return its hierarchy node.');
        }
        $this->projection->recordFacilityWouldCreate($request->code, $result->projectedNode);

        return new ImportRowOutcome(true, code: 'would_create', message: 'Would create this facility.');
      }

      return new ImportRowOutcome(true, $result->resourceId);
    }

    return match ($result->outcome) {
      ProvisionOutcome::QUOTA_EXCEEDED => new ImportRowOutcome(false, code: 'quota_exceeded', message: $result->message ?? 'The plan quota for facilities has been reached.'),
      ProvisionOutcome::INVALID => new ImportRowOutcome(false, code: 'invalid', message: $result->message ?? 'Invalid facility row.'),
    };
  }

  /**
   * Method restoreConfirmedSimulationRow
   *
   * Rebuilds stable nodes through the owner's read-only restoration capability.
   * Even an unavailable old parent retains the already confirmed quota offset.
   *
   * @access public
   *
   * @param ImportRowInput $input retained successful source row in file order
   *
   * @return void
   */
  public function restoreConfirmedSimulationRow(ImportRowInput $input): void
  {
    $request = $this->simulationRequest($input);
    $result = $this->provisioning->restoreSimulation($request);
    $this->projection->recordFacilityWouldCreate($request->code, ProvisionOutcome::CREATED === $result->outcome ? $result->projectedNode : null);
  }

  /**
   * Method simulationRequest
   *
   * Initial execution and restoration share identities derived from the retained job and row.
   *
   * @access private
   *
   * @param ImportRowInput $input original source row
   *
   * @return ProvisionFacilityRequest immutable projected owner request
   */
  private function simulationRequest(ImportRowInput $input): ProvisionFacilityRequest
  {
    $request = $this->rows->map($this->context->organizationId, $input->row);

    return new ProvisionFacilityRequest(
      organizationId: $request->organizationId,
      type: $request->type,
      name: $request->name,
      code: $request->code,
      address: $request->address,
      latitude: $request->latitude,
      longitude: $request->longitude,
      parentCode: $request->parentCode,
      dryRun: true,
      quotaProjectionOffset: $this->projection->facilityCount(),
      resourceId: FacilityImportProjection::simulatedFacilityId($this->context->jobId, $input->rowNumber),
      pendingCodeIds: $this->projection->facilityPendingCodeIds(),
      projectedHierarchy: $this->projection->facilityHierarchy(),
    );
  }
  // #endregion
}
