<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\SetFacilityPlanGeometry;

use Facility\Application\Port\Outbound\{FacilityAttachmentRepositoryPort, FacilityRepositoryPort};
use Facility\Application\Service\FacilityAttachmentAncestryGuard;
use Facility\Domain\Exception\{
  FacilityAttachmentNotFloorPlanException,
  FacilityAttachmentNotFoundException,
  FacilityNotFoundException
};
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{AttachmentKind, FacilityAttachmentId, FacilityId, FacilityOrganizationId, PlanGeometry};
use Shared\Application\Message\CommandHandler;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase SetFacilityPlanGeometryHandler.
 *
 * Validates, through ports and before any durable save, that the target
 * attachment exists, is a `FLOOR_PLAN`, and belongs to the facility itself
 * or one of its ancestors ({@see FacilityAttachmentAncestryGuard}) — the
 * business rule the Presentation layer must never re-implement. `null`
 * `attachmentId` (with `null` `points`) clears the geometry.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetFacilityPlanGeometryHandler implements CommandHandler
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
   * Method __invoke.
   *
   * Handles the corresponding use case execution.
   *
   * @since 1.0.0
   *
   * @param SetFacilityPlanGeometryCommand $command the command payload
   *
   * @return SetFacilityPlanGeometryResult the use case result
   */
  public function __invoke(SetFacilityPlanGeometryCommand $command): SetFacilityPlanGeometryResult
  {
    $facilityId = FacilityId::fromString($command->facilityId);
    $organizationId = FacilityOrganizationId::fromString($command->organizationId);

    $facility = $this->facilityRepository->findById($facilityId);

    if (null === $facility || (string) $facility->organizationId() !== (string) $organizationId) {
      throw FacilityNotFoundException::withId($command->facilityId);
    }

    if (null === $command->attachmentId && null === $command->points) {
      $facility->clearPlanGeometry();
      $this->facilityRepository->save($facility);

      return $this->result($facility);
    }

    if (null === $command->attachmentId || null === $command->points) {
      throw InvalidValueException::because('Fields "attachmentId" and "points" must be provided together, or both omitted to clear the geometry.');
    }

    $attachmentId = FacilityAttachmentId::fromString($command->attachmentId);

    $attachment = $this->attachmentRepository->findById($attachmentId);
    if (null === $attachment) {
      throw FacilityAttachmentNotFoundException::withId($command->attachmentId);
    }

    if (AttachmentKind::FLOOR_PLAN !== $attachment->kind()) {
      throw FacilityAttachmentNotFloorPlanException::forAttachment($command->attachmentId);
    }

    $this->ancestryGuard->assertBelongsToFacilityOrAncestor($facility, $attachment, $organizationId);

    $planGeometry = new PlanGeometry($command->attachmentId, $command->points);

    $facility->assignPlanGeometry($planGeometry);
    $this->facilityRepository->save($facility);

    return $this->result($facility);
  }

  /**
   * Method result.
   *
   * @since 1.0.0
   *
   * @param Facility $facility the mutated facility aggregate
   *
   * @return SetFacilityPlanGeometryResult the mapped result
   */
  private function result(Facility $facility): SetFacilityPlanGeometryResult
  {
    return new SetFacilityPlanGeometryResult(
      facilityId: (string) $facility->id(),
      organizationId: (string) $facility->organizationId(),
      parentFacilityId: $facility->parentFacilityId()?->__toString(),
      type: $facility->type()->value,
      name: (string) $facility->name(),
      code: $facility->code(),
      status: $facility->status()->value,
      address: $facility->address(),
      metadata: $facility->metadata(),
      createdAt: $facility->createdAt(),
      updatedAt: $facility->updatedAt(),
      planGeometry: $facility->planGeometry()?->toArray(),
    );
  }
  // #endregion
}
