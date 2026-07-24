<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\UpdateFacility;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase UpdateFacilityCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateFacilityCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $facilityId the facility identifier
   * @param ?string $type the facility type when provided
   * @param ?string $name the facility name when provided
   * @param ?string $code the optional facility code
   * @param ?string $address the optional address
   * @param ?float $latitude the optional latitude when provided
   * @param ?float $longitude the optional longitude when provided
   * @param ?array<string, mixed> $metadata the optional metadata when provided
   * @param bool $hasType whether type was provided
   * @param bool $hasName whether name was provided
   * @param bool $hasCode whether code was provided
   * @param bool $hasAddress whether address was provided
   * @param bool $hasLatitude whether latitude was provided
   * @param bool $hasLongitude whether longitude was provided
   * @param bool $hasMetadata whether metadata was provided
   */
  public function __construct(
    public string $organizationId,
    public string $facilityId,
    public ?string $type = null,
    public ?string $name = null,
    public ?string $code = null,
    public ?string $address = null,
    public ?float $latitude = null,
    public ?float $longitude = null,
    public ?array $metadata = null,
    public bool $hasType = false,
    public bool $hasName = false,
    public bool $hasCode = false,
    public bool $hasAddress = false,
    public bool $hasLatitude = false,
    public bool $hasLongitude = false,
    public bool $hasMetadata = false,
  ) {
  }
  // #endregion
}
