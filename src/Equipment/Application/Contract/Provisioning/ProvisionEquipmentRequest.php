<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\Provisioning;

/**
 * Contract ProvisionEquipmentRequest.
 *
 * Cross-module request to provision one piece of equipment programmatically
 * (bulk CSV import). The request is executed through
 * {@see \Equipment\Application\UseCase\Command\Equipment\CreateEquipment\CreateEquipmentHandler}
 * (the exact same use case the HTTP API uses), so the plan quota and every
 * domain invariant apply identically — there is no parallel creation path.
 *
 * `dryRun` runs the exact same validation without persisting: the quota is
 * projected via `OrganizationQuotaPort::getLimit()`/`getUsage()` (never
 * `assertCanAdd()`, which takes a persistence-scoped advisory lock this
 * request never enters). `quotaProjectionOffset` lets a caller processing
 * many rows in one dry run (the Import module) account for rows earlier in
 * the same batch that would already have consumed the quota.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ProvisionEquipmentRequest
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $type the equipment type value
   * @param ?string $subType the optional sub-type
   * @param ?string $brand the optional brand
   * @param ?string $model the optional model
   * @param ?string $serialNumber the optional serial number
   * @param ?string $locationLabel the optional location label
   * @param bool $dryRun when true, validates and projects the quota without persisting
   * @param int $quotaProjectionOffset equipment already provisionally counted earlier in the same dry run
   */
  public function __construct(
    public string $organizationId,
    public string $type,
    public ?string $subType = null,
    public ?string $brand = null,
    public ?string $model = null,
    public ?string $serialNumber = null,
    public ?string $locationLabel = null,
    public bool $dryRun = false,
    public int $quotaProjectionOffset = 0,
  ) {
  }
  // #endregion
}
