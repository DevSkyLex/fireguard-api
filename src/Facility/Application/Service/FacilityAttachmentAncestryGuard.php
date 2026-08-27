<?php

declare(strict_types=1);

namespace Facility\Application\Service;

use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\Exception\FacilityAttachmentNotAncestorException;
use Facility\Domain\Model\Attachment\FacilityAttachment;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\FacilityOrganizationId;

/**
 * Service FacilityAttachmentAncestryGuard.
 *
 * Shared by the write (`SetFacilityPlanGeometry`) and read
 * (`GetFacilityPlanOverlay`) use cases: a plan geometry may only be bound to
 * a floor plan attachment that belongs to the target facility itself or to
 * one of its ancestors — never a sibling's, a descendant's, or a facility in
 * another organization's attachment.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityAttachmentAncestryGuard
{
  // #region Constructor
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method assertBelongsToFacilityOrAncestor.
   *
   * Walks the facility's ancestry from itself upward, stopping the first
   * time the attachment's own facility is found. Cycle-safe: a facility
   * already visited breaks the walk instead of looping forever.
   *
   * @since 1.0.0
   *
   * @param Facility $facility the target facility (self counts)
   * @param FacilityAttachment $attachment the floor plan attachment being bound
   * @param FacilityOrganizationId $organizationId the organization the facility must belong to
   *
   * @throws FacilityAttachmentNotAncestorException when the attachment belongs to neither the facility nor an ancestor
   */
  public function assertBelongsToFacilityOrAncestor(
    Facility $facility,
    FacilityAttachment $attachment,
    FacilityOrganizationId $organizationId,
  ): void {
    $attachmentFacilityId = (string) $attachment->facilityId();

    if ($attachmentFacilityId === (string) $facility->id()) {
      return;
    }

    $current = $facility;
    $visited = [(string) $facility->id() => true];

    while (null !== $current->parentFacilityId()) {
      $parentId = $current->parentFacilityId();
      $parentIdString = (string) $parentId;

      if (isset($visited[$parentIdString])) {
        break;
      }

      $visited[$parentIdString] = true;

      if ($attachmentFacilityId === $parentIdString) {
        return;
      }

      $parent = $this->facilityRepository->findById($parentId);
      if (null === $parent || (string) $parent->organizationId() !== (string) $organizationId) {
        break;
      }

      $current = $parent;
    }

    throw FacilityAttachmentNotAncestorException::forAttachment((string) $attachment->id(), (string) $facility->id());
  }
  // #endregion
}
