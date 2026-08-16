<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacility;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetFacilityResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $metadata the optional metadata
   * @param int $equipmentCount active equipment assigned to this facility, read
   *                            through the Equipment module's outbound port —
   *                            the facility itself owns no equipment data
   * @param list<array{id: string, name: string, type: string}> $path ancestor
   *                                                                  breadcrumb ordered root first, direct parent
   *                                                                  last, excluding this facility
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
    public bool $hasChildren = false,
    public ?float $latitude = null,
    public ?float $longitude = null,
    public int $equipmentCount = 0,
    public array $path = [],
  ) {
  }
  // #endregion
}
