<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\CreateFacility;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase CreateFacilityResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateFacilityResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $facilityId the facility identifier
   * @param string $organizationId the organization identifier
   * @param ?string $parentFacilityId the optional parent facility identifier
   * @param string $type the facility type
   * @param string $name the facility name
   * @param ?string $code the optional facility code
   * @param string $status the facility status
   * @param ?string $address the optional address
   * @param ?float $latitude the optional latitude
   * @param ?float $longitude the optional longitude
   * @param array<string, mixed> $metadata the optional metadata
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the update timestamp
   * @param ?int $levelIndex the optional stacking order of the floor (ground floor = 0, first basement = -1)
   */
  public function __construct(
    public string $facilityId,
    public string $organizationId,
    public ?string $parentFacilityId,
    public string $type,
    public string $name,
    public ?string $code,
    public string $status,
    public ?string $address,
    public array $metadata,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public ?float $latitude = null,
    public ?float $longitude = null,
    public ?int $levelIndex = null,
  ) {
  }
  // #endregion
}
