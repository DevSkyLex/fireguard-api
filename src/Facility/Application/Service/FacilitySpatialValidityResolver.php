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

    return $this->authorizedGeometryIssue($context, $facilityId, $geometry, $attachmentId, $renderedPlanId);
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
    if ($this->positionIsInvalid($position, $invalid)) {
      return 'invalid_position';
    }
    if (null === $renderedPlanId) {
      return 'missing_plan';
    }

    return $this->positionPlanIssue($context, $facilityId, $position, $renderedPlanId);
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

  /**
   * Method authorizedGeometryIssue.
   *
   * Inspects polygon shape before comparing the accessible plan with ancestry and the rendered frame.
   *
   * @access private
   *
   * @param FacilitySpatialContext $context the authorized plan snapshot
   * @param string $facilityId the facility carrying the polygon
   * @param array<string, mixed> $geometry the retained polygon
   * @param string $attachmentId its already resolved accessible plan
   * @param ?string $renderedPlanId the explicit frame, absent to use the nearest primary plan
   *
   * @return 'invalid_geometry'|'outside_ancestry'|'other_plan'|null the authorized polygon diagnostic
   */
  private function authorizedGeometryIssue(FacilitySpatialContext $context, string $facilityId, array $geometry, string $attachmentId, ?string $renderedPlanId): ?string
  {
    if (!PlanGeometry::isUsable($geometry)) {
      return 'invalid_geometry';
    }

    return $this->placementIssue($context, $facilityId, $attachmentId, $renderedPlanId ?? $context->primaryPlan($facilityId));
  }

  /**
   * Method positionIsInvalid.
   *
   * Rejects invalid persisted coordinates before a missing frame or placement is reported.
   *
   * @access private
   *
   * @param ?array{attachmentId: string, x: float, y: float} $position the retained normalized position
   * @param bool $invalid the reader's rejection of persisted position data
   *
   * @return bool whether the position must be omitted as invalid
   */
  private function positionIsInvalid(?array $position, bool $invalid): bool
  {
    return $invalid || (null !== $position && (!is_finite($position['x']) || !is_finite($position['y']) || $position['x'] < 0 || $position['x'] > 1 || $position['y'] < 0 || $position['y'] > 1));
  }

  /**
   * Method positionPlanIssue.
   *
   * Separates absent positions and unreadable plan references from authorized placement checks.
   *
   * @access private
   *
   * @param FacilitySpatialContext $context the authorized plan snapshot
   * @param string $facilityId the equipment assignment
   * @param ?array{attachmentId: string, x: float, y: float} $position the valid retained position, when present
   * @param string $renderedPlanId the resolved rendered frame
   *
   * @return 'unplaced'|'other_plan'|'outside_ancestry'|null the placement diagnostic
   */
  private function positionPlanIssue(FacilitySpatialContext $context, string $facilityId, ?array $position, string $renderedPlanId): ?string
  {
    if (null === $position) {
      return 'unplaced';
    }
    if (!isset($context->plans[$position['attachmentId']])) {
      return 'other_plan';
    }

    return $this->placementIssue($context, $facilityId, $position['attachmentId'], $renderedPlanId);
  }

  /**
   * Method placementIssue.
   *
   * An accessible plan outside ancestry takes precedence over a different rendered frame.
   *
   * @access private
   *
   * @param FacilitySpatialContext $context the authorized plan snapshot
   * @param string $facilityId the facility whose ancestors constrain placement
   * @param string $attachmentId an already resolved accessible plan
   * @param ?string $renderedPlanId the view's selected frame, absent when no primary exists
   *
   * @return 'outside_ancestry'|'other_plan'|null the authorized plan placement diagnostic
   */
  private function placementIssue(FacilitySpatialContext $context, string $facilityId, string $attachmentId, ?string $renderedPlanId): ?string
  {
    if (!in_array($context->plans[$attachmentId]['facilityId'], $context->ancestors($facilityId), true)) {
      return 'outside_ancestry';
    }

    return null !== $renderedPlanId && $attachmentId !== $renderedPlanId ? 'other_plan' : null;
  }
  // #endregion
}
