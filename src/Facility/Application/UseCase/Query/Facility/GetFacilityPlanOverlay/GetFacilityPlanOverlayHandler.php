<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityPlanOverlay;

use Facility\Application\Port\Outbound\{
  FacilityAttachmentRepositoryPort,
  FacilityEquipmentPlanPositionPort,
  FacilityRepositoryPort
};
use Facility\Application\Service\{FacilityAttachmentAncestryGuard, FacilitySpatialValidityResolver};
use Facility\Domain\Exception\{
  FacilityAttachmentNotFloorPlanException,
  FacilityAttachmentNotFoundException,
  FacilityNotFoundException
};
use Facility\Domain\Model\Attachment\FacilityAttachment;
use Facility\Domain\ValueObject\{AttachmentKind, FacilityAttachmentId, FacilityId, FacilityOrganizationId};
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetFacilityPlanOverlayHandler.
 *
 * Resolves one floor plan — explicit `attachmentId`, or the facility's own
 * primary plan when omitted — and lists every published self-or-descendant
 * zone bound to it ({@see FacilityRepositoryPort::findZonesForPlanAttachment()}),
 * plus every equipment item pinned on the same attachment, resolved
 * cross-module through {@see FacilityEquipmentPlanPositionPort} (implemented
 * by Equipment's Infrastructure — this handler never queries Equipment's
 * storage directly).
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityPlanOverlayHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives facility, attachment, ancestry, and equipment-position capabilities used to assemble a plan overlay.
   *
   * @access public
   *
   * @param FacilityRepositoryPort $facilityRepository port used to load the requested facility and ancestors
   * @param FacilityAttachmentRepositoryPort $attachmentRepository port used to find plan attachment metadata
   * @param FacilityAttachmentAncestryGuard $ancestryGuard service that validates selected attachment ancestry
   * @param FacilityEquipmentPlanPositionPort $equipmentPlanPosition port used to retrieve equipment positions for the overlay
   *
   * @return void
   */
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
    private FacilityAttachmentRepositoryPort $attachmentRepository,
    private FacilityAttachmentAncestryGuard $ancestryGuard,
    private FacilitySpatialValidityResolver $spatial,
    private FacilityEquipmentPlanPositionPort $equipmentPlanPosition,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the corresponding use case execution.
   *
   * @since 1.0.0
   *
   * @param GetFacilityPlanOverlayQuery $query the query payload
   *
   * @return GetFacilityPlanOverlayResult the use case result
   */
  public function __invoke(GetFacilityPlanOverlayQuery $query): GetFacilityPlanOverlayResult
  {
    $facilityId = FacilityId::fromString($query->facilityId);
    $organizationId = FacilityOrganizationId::fromString($query->organizationId);

    $facility = $this->facilityRepository->findById($facilityId);

    if (null === $facility || (string) $facility->organizationId() !== (string) $organizationId) {
      throw FacilityNotFoundException::withId($query->facilityId);
    }

    $attachment = $this->resolvePlanAttachment($query->attachmentId, $facilityId);

    if (AttachmentKind::FLOOR_PLAN !== $attachment->kind()) {
      throw FacilityAttachmentNotFloorPlanException::forAttachment((string) $attachment->id());
    }

    $this->ancestryGuard->assertBelongsToFacilityOrAncestor($facility, $attachment, $organizationId);

    $zones = $this->facilityRepository->findZonesForPlanAttachment(
      $organizationId,
      $facilityId,
      (string) $attachment->id(),
    );

    $equipment = $this->equipmentPlanPosition->findEquipmentPlacedOnPlan(
      (string) $organizationId,
      (string) $attachment->id(),
    );
    $facilityIds = [(string) $facilityId];
    $attachmentIds = [(string) $attachment->id()];
    foreach ($zones as $zone) {
      $facilityIds[] = $zone['facilityId'];
      $attachmentIds[] = $zone['attachmentId'];
    }
    foreach ($equipment as $item) {
      if (null !== $item['facilityId']) {
        $facilityIds[] = $item['facilityId'];
      }
    }
    $spatialContext = $this->spatial->context((string) $organizationId, $facilityIds, $attachmentIds);
    $validZones = [];
    $geometryIssues = [];
    foreach ($zones as $zone) {
      $issue = $this->spatial->geometryIssue($spatialContext, $zone['facilityId'], ['attachmentId' => $zone['attachmentId'], 'points' => $zone['points']], (string) $attachment->id());
      if (null !== $issue) {
        $geometryIssues[] = ['facilityId' => $zone['facilityId'], 'code' => $issue];
      } else {
        unset($zone['attachmentId']);
        $validZones[] = $zone;
      }
    }
    $validEquipment = [];
    $equipmentIssues = [];
    foreach ($equipment as $item) {
      $position = ['attachmentId' => (string) $attachment->id(), 'x' => $item['x'], 'y' => $item['y']];
      $issue = $this->spatial->positionIssue($spatialContext, $item['facilityId'] ?? '', $position, (string) $attachment->id(), $item['invalidPosition']);
      if (null !== $issue) {
        $equipmentIssues[] = ['equipmentId' => $item['equipmentId'], 'code' => $issue];
      } else {
        unset($item['facilityId'], $item['invalidPosition']);
        $validEquipment[] = $item;
      }
    }

    return new GetFacilityPlanOverlayResult(
      attachmentId: (string) $attachment->id(),
      imageWidth: $attachment->imageWidth(),
      imageHeight: $attachment->imageHeight(),
      zones: $validZones,
      equipment: $validEquipment,
      geometryIssues: $geometryIssues,
      equipmentIssues: $equipmentIssues,
    );
  }

  /**
   * Method resolvePlanAttachment.
   *
   * Resolves the explicit `attachmentId`, or falls back to the facility's
   * own primary floor plan when omitted.
   *
   * @since 1.0.0
   *
   * @param ?string $attachmentId the explicit attachment identifier, if any
   * @param FacilityId $facilityId the facility the overlay was requested for
   *
   * @return FacilityAttachment the resolved plan attachment
   */
  private function resolvePlanAttachment(?string $attachmentId, FacilityId $facilityId): FacilityAttachment
  {
    if (null === $attachmentId) {
      $primary = $this->attachmentRepository->findPrimaryFloorPlan($facilityId);
      if (null === $primary) {
        throw FacilityAttachmentNotFoundException::withId('primary plan for facility ' . (string) $facilityId);
      }

      return $primary;
    }

    $id = FacilityAttachmentId::fromString($attachmentId);

    $attachment = $this->attachmentRepository->findById($id);
    if (null === $attachment) {
      throw FacilityAttachmentNotFoundException::withId($attachmentId);
    }

    return $attachment;
  }
  // #endregion
}
