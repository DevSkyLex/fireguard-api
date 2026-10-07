<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\CreateFacility;

use Facility\Application\Contract\Hierarchy\FacilityHierarchyNode;
use Onboarding\Application\Contract\Setup\OrganizationSetupContext;
use Shared\Application\Message\CommandMessage;

/**
 * UseCase CreateFacilityCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateFacilityCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $type the facility type
   * @param string $name the facility name
   * @param ?string $parentFacilityId the optional parent facility identifier
   * @param ?string $code the optional facility code
   * @param ?string $address the optional address
   * @param ?float $latitude the optional latitude, required together with longitude
   * @param ?float $longitude the optional longitude, required together with latitude
   * @param array<string, mixed> $metadata the optional metadata
   * @param ?string $resourceId the resource id value
   * @param bool $dryRun when true, validates and projects the quota without persisting
   * @param int $quotaProjectionOffset facilities already provisionally counted earlier in the same dry run
   * @param ?int $levelIndex the optional stacking order of the floor (ground floor = 0, first basement = -1)
   * @param ?float $elevationMeters optional physical floor elevation in meters
   * @param ?float $heightMeters optional physical floor height in meters
   * @param list<FacilityHierarchyNode> $projectedHierarchy preceding staged rows in an import simulation
   */
  public function __construct(
    public string $organizationId,
    public string $type,
    public string $name,
    public ?string $parentFacilityId = null,
    public ?string $code = null,
    public ?string $address = null,
    public ?float $latitude = null,
    public ?float $longitude = null,
    public array $metadata = [],
    public ?string $resourceId = null,
    public bool $dryRun = false,
    public int $quotaProjectionOffset = 0,
    public ?int $levelIndex = null,
    public ?float $elevationMeters = null,
    public ?float $heightMeters = null,
    public ?OrganizationSetupContext $setupContext = null,
    public ?string $interventionId = null,
    public array $projectedHierarchy = [],
    public ?string $customerId = null,
  ) {
  }
  // #endregion
}
