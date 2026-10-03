<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Attachment\SetFacilityAttachmentCalibration;

use Facility\Application\Port\Outbound\{FacilityAttachmentRepositoryPort, FacilityRepositoryPort};
use Facility\Application\Service\FacilitySpatialValidityResolver;
use Facility\Domain\Exception\{FacilityAccessDeniedException, FacilityAttachmentNotFoundException, FacilityRevisionMismatchException};
use Facility\Domain\ValueObject\{FacilityAttachmentId, PlanCalibration};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\TransactionManagerPort;

/**
 * Handler SetFacilityAttachmentCalibrationHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetFacilityAttachmentCalibrationHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   */
  public function __construct(
    private FacilityAttachmentRepositoryPort $attachmentRepository,
    private FacilityRepositoryPort $facilityRepository,
    private OrganizationAuthorizationPort $authorization,
    private TransactionManagerPort $transactionManager,
    private FacilitySpatialValidityResolver $spatial,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(SetFacilityAttachmentCalibrationCommand $command): SetFacilityAttachmentCalibrationResult
  {
    return $this->transactionManager->transactional(function () use ($command): SetFacilityAttachmentCalibrationResult {
      $attachment = $this->attachmentRepository->findById(FacilityAttachmentId::fromString($command->attachmentId));
      $facility = null === $attachment ? null : $this->facilityRepository->findById($attachment->facilityId());
      if (null === $attachment || null === $facility) {
        throw FacilityAttachmentNotFoundException::withId($command->attachmentId);
      }
      $decision = $this->authorization->resolveAccess($command->userId, (string) $facility->organizationId(), 'organization.facilities.write');
      if ($decision->isOutsideScope()) {
        throw FacilityAttachmentNotFoundException::withId($command->attachmentId);
      }
      if (!$decision->isGranted()) {
        throw new FacilityAccessDeniedException('Missing organization.facilities.write permission.');
      }
      if ($attachment->revision() !== $command->expectedRevision) {
        throw FacilityRevisionMismatchException::stale();
      }

      $calibration = null === $command->calibration ? null : PlanCalibration::fromArray($command->calibration);
      $buildingId = null === $calibration ? null : $this->spatial->buildingIdForFacility((string) $facility->organizationId(), (string) $facility->id());
      $attachment->calibrate($calibration, $buildingId);
      $this->attachmentRepository->saveCalibration($attachment, $command->expectedRevision);

      return new SetFacilityAttachmentCalibrationResult(
        id: (string) $attachment->id(),
        facilityId: (string) $attachment->facilityId(),
        fileName: $attachment->fileName(),
        mimeType: $attachment->mimeType(),
        size: $attachment->size(),
        label: $attachment->label(),
        revision: $attachment->revision(),
        kind: $attachment->kind()->value,
        isPrimaryPlan: $attachment->isPrimaryPlan(),
        imageWidth: $attachment->imageWidth(),
        imageHeight: $attachment->imageHeight(),
        uploadedAt: $attachment->uploadedAt()->format('c'),
        calibration: $attachment->calibration()?->toArray(),
        calibrationBuildingId: $attachment->calibrationBuildingId(),
        calibrationIssue: null !== $attachment->calibration() && null === $attachment->calibrationBuildingId() ? 'unverified_frame' : null,
      );
    });
  }
  // #endregion
}
