<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Attachment\SetPrimaryFacilityAttachment;

use Facility\Application\Port\Outbound\{FacilityAttachmentRepositoryPort, FacilityRepositoryPort};
use Facility\Domain\Exception\{FacilityAttachmentNotFoundException, FacilityNotFoundException};
use Facility\Domain\ValueObject\{FacilityAttachmentId, FacilityId, FacilityOrganizationId};
use InvalidArgumentException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase SetPrimaryFacilityAttachmentHandler.
 *
 * Promotes one `FLOOR_PLAN` attachment to be the facility's primary plan,
 * atomically clearing the flag of whichever attachment carried it before —
 * "atomic" here means the clear and the promotion commit inside the same
 * database transaction, never leaving the facility with zero or two primary
 * plans observable to a concurrent reader. Refuses (409) when the target
 * attachment is not a floor plan; the domain invariant itself lives on
 * `FacilityAttachment::markAsPrimary()`, this handler only supplies the
 * transaction boundary and the atomic clear of the previous primary.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetPrimaryFacilityAttachmentHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
    private FacilityAttachmentRepositoryPort $attachmentRepository,
    private TransactionManagerPort $transactionManager,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(SetPrimaryFacilityAttachmentCommand $command): SetPrimaryFacilityAttachmentResult
  {
    try {
      $facilityId = FacilityId::fromString($command->facilityId);
      $organizationId = FacilityOrganizationId::fromString($command->organizationId);
      $attachmentId = FacilityAttachmentId::fromString($command->attachmentId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $facility = $this->facilityRepository->findById($facilityId);

    if (null === $facility || (string) $facility->organizationId() !== (string) $organizationId) {
      throw FacilityNotFoundException::withId($command->facilityId);
    }

    $attachment = $this->attachmentRepository->findById($attachmentId);

    if (null === $attachment || (string) $attachment->facilityId() !== (string) $facilityId) {
      throw FacilityAttachmentNotFoundException::withId($command->attachmentId);
    }

    // Validated BEFORE the transaction opens: a document attachment must
    // refuse (FacilityAttachmentNotFloorPlanException, mapped to 409) without
    // ever touching the previous primary's flag.
    $attachment->markAsPrimary();

    $this->transactionManager->transactional(function () use ($facilityId, $attachmentId, $attachment): void {
      $this->attachmentRepository->clearPrimaryPlan($facilityId, $attachmentId);
      $this->attachmentRepository->save($attachment);
    });

    return new SetPrimaryFacilityAttachmentResult(
      attachmentId: (string) $attachment->id(),
      facilityId: (string) $attachment->facilityId(),
      fileName: $attachment->fileName(),
      mimeType: $attachment->mimeType(),
      size: $attachment->size(),
      label: $attachment->label(),
      kind: $attachment->kind()->value,
      isPrimaryPlan: $attachment->isPrimaryPlan(),
      imageWidth: $attachment->imageWidth(),
      imageHeight: $attachment->imageHeight(),
    );
  }
  // #endregion
}
