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

    $kind = $this->attachmentKind($command->kind);
    $attachmentId = null === $command->attachmentId
      ? FacilityAttachmentId::fromString($this->uuidFactory->generate())
      : FacilityAttachmentId::fromString($command->attachmentId);
    $storagePath = null;

    try {
      $attachment = $this->transactionManager->transactional(function () use ($command, $facilityId, $organizationId, $attachmentId, $kind, &$storagePath): FacilityAttachment {
        return $this->storeAttachment($command, $facilityId, $organizationId, $attachmentId, $kind, $storagePath);
      });
    } catch (CommitOutcomeUnknownException $exception) {
      throw $exception;
    } catch (Throwable $exception) {
      /** @var ?string $storagePath The transaction callback owns this attempt path. */
      $this->compensateAttempt($storagePath);

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

  /**
   * Method attachmentKind
   *
   * Translates an invalid attachment kind into the existing validation contract.
   *
   * @access private
   *
   * @param string $kind requested attachment kind
   *
   * @return AttachmentKind validated attachment kind
   */
  private function attachmentKind(string $kind): AttachmentKind
  {
    try {
      return AttachmentKind::from($kind);
    } catch (ValueError $exception) {
      throw InvalidValueException::because($exception->getMessage(), $exception);
    }
  }

  /**
   * Method storeAttachment
   *
   * Keeps parent locking, replay and attempt cleanup inside the owning transaction.
   *
   * @access private
   *
   * @param AddFacilityAttachmentCommand $command attachment input
   * @param FacilityId $facilityId parent identity
   * @param FacilityOrganizationId $organizationId expected parent scope
   * @param FacilityAttachmentId $attachmentId replay identity
   * @param AttachmentKind $kind validated attachment kind
   * @param ?string $storagePath attempt path owned by the enclosing compensation
   *
   * @return FacilityAttachment persisted attachment or committed replay winner
   */
  private function storeAttachment(AddFacilityAttachmentCommand $command, FacilityId $facilityId, FacilityOrganizationId $organizationId, FacilityAttachmentId $attachmentId, AttachmentKind $kind, ?string &$storagePath): FacilityAttachment
  {
    $this->attachmentRepository->lock($facilityId);
    $this->assertFacilityAccess($command, $facilityId, $organizationId);
    $existing = $this->attachmentRepository->findById($attachmentId);
    if (null !== $existing) {
      return $this->requireAttachmentParent($existing, $command->facilityId);
    }
    AttachmentConstraints::validateCount($this->attachmentRepository->countByFacilityId($facilityId));
    $attachment = $this->createAttachment($command, $facilityId, $attachmentId, $kind);
    $storagePath = $attachment->file()->storagePath;
    $this->fileStorage->write($storagePath, $command->contents);
    if (!$this->attachmentRepository->saveIfAbsent($attachment)) {
      $winner = $this->attachmentRepository->findById($attachmentId);
      $winner = $this->requireAttachmentParent($winner, $command->facilityId);
      $this->cleanup->request($storagePath);

      return $winner;
    }

    return $attachment;
  }

  /**
   * Method assertFacilityAccess
   *
   * Checks the locked parent scope before permission and replay checks.
   *
   * @access private
   *
   * @param AddFacilityAttachmentCommand $command attachment input and actor
   * @param FacilityId $facilityId locked parent identity
   * @param FacilityOrganizationId $organizationId expected parent scope
   *
   * @return void
   */
  private function assertFacilityAccess(AddFacilityAttachmentCommand $command, FacilityId $facilityId, FacilityOrganizationId $organizationId): void
  {
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
  }

  /**
   * Method requireAttachmentParent
   *
   * Rejects replay identities outside the locked parent without revealing them.
   *
   * @access private
   *
   * @param ?FacilityAttachment $attachment replay or insert winner
   * @param string $facilityId expected parent identity
   *
   * @return FacilityAttachment replay or insert winner from the expected parent
   */
  private function requireAttachmentParent(?FacilityAttachment $attachment, string $facilityId): FacilityAttachment
  {
    if (null === $attachment || (string) $attachment->facilityId() !== $facilityId) {
      throw new FacilityAttachmentNotFoundException('Attachment not found.');
    }

    return $attachment;
  }

  /**
   * Method createAttachment
   *
   * Allocates a storage identity for this attempt independently of replay identity.
   *
   * @access private
   *
   * @param AddFacilityAttachmentCommand $command file input
   * @param FacilityId $facilityId locked parent identity
   * @param FacilityAttachmentId $attachmentId replay identity
   * @param AttachmentKind $kind validated attachment kind
   *
   * @return FacilityAttachment validated upload metadata
   */
  private function createAttachment(AddFacilityAttachmentCommand $command, FacilityId $facilityId, FacilityAttachmentId $attachmentId, AttachmentKind $kind): FacilityAttachment
  {
    $attemptPath = StoragePathScheme::build('facility', $command->facilityId, $this->uuidFactory->generate(), $command->fileName);
    $dimensions = AttachmentKind::FLOOR_PLAN === $kind ? ImageDimensions::fromContents($command->contents, $command->mimeType) : null;

    return FacilityAttachment::create(
      $attachmentId,
      $facilityId,
      $command->fileName,
      $attemptPath,
      $command->mimeType,
      $command->size,
      new FacilityAttachmentCreationOptions($command->label, $kind, $dimensions?->width(), $dimensions?->height()),
    );
  }

  /**
   * Method compensateAttempt
   *
   * Removes only the failed attempt while retaining the original transaction failure.
   *
   * @access private
   *
   * @param ?string $storagePath attempt path assigned before writing bytes
   *
   * @return void
   */
  private function compensateAttempt(?string $storagePath): void
  {
    if (null !== $storagePath) {
      try {
        $this->fileStorage->delete($storagePath);
      } catch (Throwable) {
        // Preserve the transaction failure when compensation also fails.
      }
    }
  }
  // #endregion
}
