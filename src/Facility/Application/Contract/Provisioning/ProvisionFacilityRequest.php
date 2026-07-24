<?php

declare(strict_types=1);

namespace Facility\Application\Contract\Provisioning;

/**
 * Contract ProvisionFacilityRequest.
 *
 * Cross-module request to provision one facility programmatically (bulk CSV
 * import). The request is executed through
 * {@see \Facility\Application\UseCase\Command\Facility\CreateFacility\CreateFacilityHandler}
 * (the exact same use case the HTTP API uses), so the plan quota and every
 * domain invariant apply identically. `parentCode` is resolved to a parent
 * facility identifier inside {@see \Facility\Application\Service\FacilityProvisioningService},
 * so callers never need to know a facility's internal identifier.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ProvisionFacilityRequest
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $type the facility type value
   * @param string $name the facility name
   * @param ?string $code the optional facility code
   * @param ?string $address the optional address
   * @param ?float $latitude the optional latitude, required together with longitude
   * @param ?float $longitude the optional longitude, required together with latitude
   * @param ?string $parentCode the optional parent facility code, resolved internally
   */
  public function __construct(
    public string $organizationId,
    public string $type,
    public string $name,
    public ?string $code = null,
    public ?string $address = null,
    public ?float $latitude = null,
    public ?float $longitude = null,
    public ?string $parentCode = null,
  ) {
  }
  // #endregion
}
