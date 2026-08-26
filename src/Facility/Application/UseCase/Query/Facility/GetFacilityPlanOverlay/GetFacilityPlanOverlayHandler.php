<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityPlanOverlay;

use Facility\Application\Port\Outbound\{
  FacilityAttachmentRepositoryPort,
  FacilityEquipmentPlanPositionPort,
  FacilityRepositoryPort
};
use Facility\Application\Service\FacilityAttachmentAncestryGuard;
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
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityPlanOverlayHandler implements QueryHandler
{
  // #region Constructor
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
    private FacilityAttachmentRepositoryPort $attachmentRepository,
    private FacilityAttachmentAncestryGuard $ancestryGuard,
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

    return new GetFacilityPlanOverlayResult(
      attachmentId: (string) $attachment->id(),
      imageWidth: $attachment->imageWidth(),
      imageHeight: $attachment->imageHeight(),
      zones: $zones,
      equipment: $equipment,
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
