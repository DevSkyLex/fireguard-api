<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\SetFacilityPlanGeometry;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase SetFacilityPlanGeometryCommand.
 *
 * `attachmentId` and `points` travel together: both `null` clears the
 * facility's geometry, both set assign it. A caller providing only one of
 * the two is a handler-level validation error (400), not a partial update —
 * mirrors `UpdateFacilityCommand`'s latitude/longitude pairing.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetFacilityPlanGeometryCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $facilityId the target facility identifier
   * @param ?string $attachmentId the floor plan attachment identifier, or null to clear
   * @param ?list<array{0: float, 1: float}> $points the polygon points, or null to clear
   */
  public function __construct(
    public string $organizationId,
    public string $facilityId,
    public ?string $attachmentId,
    public ?array $points,
  ) {
  }
  // #endregion
}
