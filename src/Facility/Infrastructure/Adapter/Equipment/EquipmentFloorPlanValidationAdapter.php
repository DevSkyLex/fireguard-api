<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Equipment;

use Equipment\Application\Port\Outbound\EquipmentFloorPlanValidationPort;
use Equipment\Domain\Exception\{
  FloorPlanAttachmentNotAncestorException,
  FloorPlanAttachmentNotFloorPlanException,
  FloorPlanAttachmentNotFoundException
};
use Facility\Application\Port\Outbound\{FacilityAttachmentRepositoryPort, FacilityRepositoryPort};
use Facility\Application\Service\FacilityAttachmentAncestryGuard;
use Facility\Domain\Exception\FacilityAttachmentNotAncestorException as DomainFacilityAttachmentNotAncestorException;
use Facility\Domain\ValueObject\{AttachmentKind, FacilityAttachmentId, FacilityId};
use Shared\Domain\Exception\InvalidValueException;

/**
 * Adapter EquipmentFloorPlanValidationAdapter.
 *
 * Implements the Equipment module's floor-plan validation port using the
 * Facility module's repositories and its existing
 * `FacilityAttachmentAncestryGuard` — reused as-is rather than duplicated,
 * since the ancestry walk is a Facility-owned rule regardless of which
 * module is asking. Mirrors
 * `Facility\Infrastructure\Adapter\Equipment\FacilityValidationAdapter`'s
 * direction: Facility Infrastructure implements Equipment's outbound port.
 * Every failure is translated into one of Equipment's own typed Domain
 * exceptions — this adapter is the only place in the codebase allowed to
 * import both modules' Domain layers, because it lives in Facility (which
 * may depend on its own Domain) and only ever *catches* Facility's
 * exception to re-throw Equipment's.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentFloorPlanValidationAdapter implements EquipmentFloorPlanValidationPort
{
  // #region Constructor
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
    private FacilityAttachmentRepositoryPort $attachmentRepository,
    private FacilityAttachmentAncestryGuard $ancestryGuard,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function assertAttachmentUsableForFacility(string $attachmentId, string $facilityId): void
  {
    try {
      $facilityIdVo = FacilityId::fromString($facilityId);
      $attachmentIdVo = FacilityAttachmentId::fromString($attachmentId);
    } catch (InvalidValueException) {
      // A malformed id cannot resolve to a real attachment either way; the
      // Input DTO's own `#[Assert\Uuid]` is the primary gate, this is
      // defense in depth against a caller that bypassed it.
      throw FloorPlanAttachmentNotFoundException::withId($attachmentId);
    }

    // Not attributable to the equipment's own facility assignment already
    // having been validated on assignment — kept as a defensive fallback,
    // reported as the attachment being unresolvable rather than inventing an
    // exception type the port contract does not declare.
    $facility = $this->facilityRepository->findById($facilityIdVo);

    if (null === $facility) {
      throw FloorPlanAttachmentNotFoundException::withId($attachmentId);
    }

    $attachment = $this->attachmentRepository->findById($attachmentIdVo);

    if (null === $attachment) {
      throw FloorPlanAttachmentNotFoundException::withId($attachmentId);
    }

    if (AttachmentKind::FLOOR_PLAN !== $attachment->kind()) {
      throw FloorPlanAttachmentNotFloorPlanException::forAttachment($attachmentId);
    }

    try {
      $this->ancestryGuard->assertBelongsToFacilityOrAncestor($facility, $attachment, $facility->organizationId());
    } catch (DomainFacilityAttachmentNotAncestorException) {
      throw FloorPlanAttachmentNotAncestorException::forAttachment($attachmentId, $facilityId);
    }
  }
  // #endregion
}
