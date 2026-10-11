<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Attachment\AddFacilityAttachment;

use Facility\Application\Port\Outbound\Internal\{FacilityAttachmentRepositoryPort, FacilityRepositoryPort};
use Facility\Domain\Exception\{FacilityAccessDeniedException, FacilityAttachmentNotFoundException};
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Domain\Model\Attachment\{FacilityAttachment, FacilityAttachmentCreationOptions};
use Facility\Domain\ValueObject\{AttachmentKind, FacilityAttachmentId, FacilityId, FacilityOrganizationId, ImageDimensions};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Contract\Transaction\CommitOutcomeUnknownException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{FileCleanupPort, FileStoragePort, RootTransactionManagerPort, UuidGeneratorPort};
use Shared\Domain\Attachment\{AttachmentConstraints, StoragePathScheme};
use Shared\Domain\Exception\InvalidValueException;
use Throwable;
use ValueError;

/**
 * UseCase AddFacilityAttachmentHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddFacilityAttachmentHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives facility and attachment repositories, file storage, and an identifier factory for attachment creation.
   *
   * @access public
   *
   * @param FacilityRepositoryPort $facilityRepository port used to validate the facility and its organization scope
   * @param FacilityAttachmentRepositoryPort $attachmentRepository port used to check attachment identity and persist metadata
   * @param FileStoragePort $fileStorage port used to write the file and remove it if persistence fails
   * @param UuidGeneratorPort $uuidFactory factory used to create an attachment identifier when none is supplied
   *
   * @return void
   */
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
    private FacilityAttachmentRepositoryPort $attachmentRepository,
    private FileStoragePort $fileStorage,
    private UuidGeneratorPort $uuidFactory,
    private RootTransactionManagerPort $transactionManager,
    private FileCleanupPort $cleanup,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(AddFacilityAttachmentCommand $command): AddFacilityAttachmentResult
  {
    $this->transactionManager->assertCanStart();
    $facilityId = FacilityId::fromString($command->facilityId);
    $organizationId = FacilityOrganizationId::fromString($command->organizationId);

    try {
      $kind = AttachmentKind::from($command->kind);
    } catch (ValueError $exception) {
      throw InvalidValueException::because($exception->getMessage(), $exception);
    }
    $attachmentId = null === $command->attachmentId
      ? FacilityAttachmentId::fromString($this->uuidFactory->generate())
      : FacilityAttachmentId::fromString($command->attachmentId);
    $storagePath = null;

    try {
      $attachment = $this->transactionManager->transactional(function () use ($command, $facilityId, $organizationId, $attachmentId, $kind, &$storagePath): FacilityAttachment {
        $this->attachmentRepository->lock($facilityId);
        $facility = $this->facilityRepository->findById($facilityId);
        if (null === $facility || (string) $facility->organizationId() !== (string) $organizationId) {
          throw FacilityNotFoundException::withId($command->facilityId);
        }
        if (null !== $command->userId) {
          $decision = $this->authorization->resolveAccess($command->userId, $command->organizationId, 'organization.facilities.write');
          if ($decision->isOutsideScope()) {
            throw new FacilityNotFoundException('Facility not found.');
          }
          if (!$decision->isGranted()) {
            throw new FacilityAccessDeniedException('Missing organization.facilities.write permission.');
          }
        }
        $existing = $this->attachmentRepository->findById($attachmentId);
        if (null !== $existing) {
          if ((string) $existing->facilityId() !== $command->facilityId) {
            throw new FacilityAttachmentNotFoundException('Attachment not found.');
          }

          return $existing;
        }
        AttachmentConstraints::validateCount($this->attachmentRepository->countByFacilityId($facilityId));
        $attemptPath = StoragePathScheme::build('facility', $command->facilityId, $this->uuidFactory->generate(), $command->fileName);
        $dimensions = AttachmentKind::FLOOR_PLAN === $kind ? ImageDimensions::fromContents($command->contents, $command->mimeType) : null;
        $attachment = FacilityAttachment::create(
          $attachmentId,
          $facilityId,
          $command->fileName,
          $attemptPath,
          $command->mimeType,
          $command->size,
          new FacilityAttachmentCreationOptions($command->label, $kind, $dimensions?->width(), $dimensions?->height()),
        );
        $storagePath = $attemptPath;
        $this->fileStorage->write($storagePath, $command->contents);
        if (!$this->attachmentRepository->saveIfAbsent($attachment)) {
          $winner = $this->attachmentRepository->findById($attachmentId);
          if (null === $winner || (string) $winner->facilityId() !== $command->facilityId) {
            throw new FacilityAttachmentNotFoundException('Attachment not found.');
          }
          $this->cleanup->request($storagePath);

          return $winner;
        }

        return $attachment;
      });
    } catch (CommitOutcomeUnknownException $exception) {
      throw $exception;
    } catch (Throwable $exception) {
      /** @var ?string $storagePath The transaction callback owns this attempt path. */
      if (null !== $storagePath) {
        try {
          $this->fileStorage->delete($storagePath);
        } catch (Throwable) {
          // Preserve the transaction failure when compensation also fails.
        }
      }

      throw $exception;
    }

    return new AddFacilityAttachmentResult(
      attachmentId: (string) $attachment->id(),
      facilityId: (string) $attachment->facilityId(),
      fileName: $attachment->file()->fileName,
      mimeType: $attachment->file()->mimeType,
      size: $attachment->file()->size,
      label: $attachment->label(),
      uploadedAt: $attachment->file()->uploadedAt,
      kind: $attachment->kind()->value,
      isPrimaryPlan: $attachment->isPrimaryPlan(),
      imageWidth: $attachment->imageWidth(),
      imageHeight: $attachment->imageHeight(),
    );
  }
  // #endregion
}
