<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\SetFacilityPlanGeometry;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase SetFacilityPlanGeometryResult.
 *
 * Carries the full facility snapshot, mirroring `MoveFacilityResult` /
 * `UpdateFacilityResult` — the Presentation layer maps every mutation
 * endpoint's result onto the same `FacilityOutput` shape.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetFacilityPlanGeometryResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $metadata the facility's metadata
   * @param ?array{attachmentId: string, points: list<array{0: float, 1: float}>} $planGeometry the resulting plan geometry, or null when cleared
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
    public ?array $planGeometry,
  ) {
  }
  // #endregion
}
