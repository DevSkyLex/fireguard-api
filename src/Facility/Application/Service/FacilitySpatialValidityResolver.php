<?php

declare(strict_types=1);

namespace Facility\Application\Service;

use Facility\Application\Contract\Spatial\FacilitySpatialContext;
use Facility\Application\Port\Outbound\FacilitySpatialReadPort;
use Facility\Domain\ValueObject\PlanGeometry;

use function in_array;
use function is_finite;
use function is_string;

/**
 * Service FacilitySpatialValidityResolver.
 *
 * Computes usability without mutating original coordinates or references.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilitySpatialValidityResolver
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Receives the organization-scoped batch read capability.
   *
   * @param FacilitySpatialReadPort $spatialRead spatial hierarchy and plan reader
   */
  public function __construct(private FacilitySpatialReadPort $spatialRead)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method context.
   *
   * @param string $organizationId organization whose resources are readable
   * @param list<string> $facilityIds
   * @param list<string> $attachmentIds
   */
  public function context(string $organizationId, array $facilityIds, array $attachmentIds = []): FacilitySpatialContext
  {
    return $this->spatialRead->readContext($organizationId, $facilityIds, $attachmentIds);
  }

  /**
   * Method buildingIdForFacility.
   *
   * Captures the current coordinate frame when explicitly calibrating a plan.
   *
   * @param string $organizationId organization owning the facility
   * @param string $facilityId attachment owner whose ancestors define the frame
   *
   * @return ?string nearest accessible building, or null for an unresolved frame
   */
  public function buildingIdForFacility(string $organizationId, string $facilityId): ?string
  {
    return $this->context($organizationId, [$facilityId])->nearest($facilityId, 'building');
  }

  /**
   * Method geometryIssue.
   *
   * @param FacilitySpatialContext $context authorized ancestry and plan snapshot
   * @param string $facilityId facility carrying the original polygon
   * @param ?array<string, mixed> $geometry
   * @param ?string $renderedPlanId plan whose coordinates the view uses
   *
   * @return 'invalid_geometry'|'plan_unavailable'|'outside_ancestry'|'other_plan'|null
   */
  public function geometryIssue(FacilitySpatialContext $context, string $facilityId, ?array $geometry, ?string $renderedPlanId = null): ?string
  {
    if (null === $geometry) {
      return null;
    }
    $attachmentId = $geometry['attachmentId'] ?? null;
    if (!is_string($attachmentId) || !isset($context->plans[$attachmentId])) {
      return 'plan_unavailable';
    }
    if (!PlanGeometry::isUsable($geometry)) {
      return 'invalid_geometry';
    }
    if (!in_array($context->plans[$attachmentId]['facilityId'], $context->ancestors($facilityId), true)) {
      return 'outside_ancestry';
    }
    $primaryId = $renderedPlanId ?? $context->primaryPlan($facilityId);
    if (null !== $primaryId && $attachmentId !== $primaryId) {
      return 'other_plan';
    }

    return null;
  }

  /**
   * Method geometryIsAuthorized.
   *
   * A retained reference may be outside the hierarchy, but never outside the organization.
   *
   * @param FacilitySpatialContext $context authorized plan snapshot
   * @param ?array<string, mixed> $geometry
   */
  public function geometryIsAuthorized(FacilitySpatialContext $context, ?array $geometry): bool
  {
    return null !== $geometry && is_string($geometry['attachmentId'] ?? null)
      && isset($context->plans[$geometry['attachmentId']]) && PlanGeometry::isUsable($geometry);
  }

  /**
   * Method positionIssue.
   *
   * @param FacilitySpatialContext $context authorized ancestry and plan snapshot
   * @param string $facilityId current equipment assignment
   * @param ?array{attachmentId: string, x: float, y: float} $position
   * @param ?string $renderedPlanId plan whose coordinates the view uses
   * @param bool $invalid whether the equipment reader rejected the persisted position
   *
   * @return 'missing_plan'|'unplaced'|'other_plan'|'invalid_position'|'outside_ancestry'|null
   */
  public function positionIssue(FacilitySpatialContext $context, string $facilityId, ?array $position, ?string $renderedPlanId, bool $invalid = false): ?string
  {
    if ($invalid || (null !== $position && (!is_finite($position['x']) || !is_finite($position['y']) || $position['x'] < 0 || $position['x'] > 1 || $position['y'] < 0 || $position['y'] > 1))) {
      return 'invalid_position';
    }
    if (null === $renderedPlanId) {
      return 'missing_plan';
    }
    if (null === $position) {
      return 'unplaced';
    }
    if (!isset($context->plans[$position['attachmentId']])) {
      return 'other_plan';
    }
    if (!in_array($context->plans[$position['attachmentId']]['facilityId'], $context->ancestors($facilityId), true)) {
      return 'outside_ancestry';
    }

    return $position['attachmentId'] === $renderedPlanId ? null : 'other_plan';
  }

  /**
   * Method calibrationIssue.
   *
   * @param FacilitySpatialContext $context authorized ancestry snapshot
   * @param string $facilityId plan owner currently being projected
   * @param ?string $calibrationBuildingId original accessible coordinate frame
   * @param bool $hasCalibration whether the attachment retains metric values
   *
   * @return 'building_changed'|'unverified_frame'|null
   */
  public function calibrationIssue(FacilitySpatialContext $context, string $facilityId, ?string $calibrationBuildingId, bool $hasCalibration): ?string
  {
    if (!$hasCalibration) {
      return null;
    }
    $buildingId = $context->nearest($facilityId, 'building');
    if (null === $buildingId || null === $calibrationBuildingId) {
      return 'unverified_frame';
    }

    return $buildingId === $calibrationBuildingId ? null : 'building_changed';
  }
  // #endregion
}
