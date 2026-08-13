<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Attachment\AddInterventionAttachment;

use Intervention\Application\Port\Outbound\InterventionAttachmentRepositoryPort;
use Intervention\Application\Service\InterventionResourceManager;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException, InterventionValidationException};
use Intervention\Domain\Model\Attachment\InterventionAttachment;
use Intervention\Domain\ValueObject\InterventionAttachmentId;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Attachment\{AttachmentConstraints, StoragePathScheme};
use Shared\Domain\Exception\InvalidValueException;
use Throwable;

/**
 * UseCase AddInterventionAttachmentHandler.
 *
 * The single source of truth for the phase-based write authorization of
 * intervention attachments: resolves the intervention's organization,
 * derives the required permission through
 * {@see InterventionResourceManager::mutationPermission()} (rejects
 * immutable states), and asserts it via {@see OrganizationAuthorizationPort}
 * — mirroring `AddInterventionCommentHandler` / `MutateInterventionWorkflowHandler`.
 * The Presentation processor performs no authorization decision of its own.
 *
 * It also enforces `AttachmentConstraints::MAX_ATTACHMENTS_PER_PARENT`: the
 * count is a rule over persisted state, so it cannot live in the
 * request-scoped `MultipartAttachmentGuard` alongside the MIME/size checks.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddInterventionAttachmentHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private InterventionResourceManager $interventionResourceManager,
    private OrganizationAuthorizationPort $authorization,
    private InterventionAttachmentRepositoryPort $attachmentRepository,
    private FileStoragePort $fileStorage,
    private UuidFactory $uuidFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(AddInterventionAttachmentCommand $command): AddInterventionAttachmentResult
  {
    $context = $this->interventionResourceManager->interventionContext($command->interventionId);

    if (null === $context) {
      throw InterventionNotFoundException::withId($command->interventionId);
    }

    // Scope gate BEFORE the permission is derived: mutationPermission() reads
    // the intervention's phase and can itself throw (a conflict on a
    // published intervention, a member-policy denial), which would tell a
    // caller outside the owning organization both that this intervention
    // exists and what state it is in.
    if (!$this->authorization->isMemberOf($command->userId, $context->organizationId)) {
      throw InterventionNotFoundException::withId($command->interventionId);
    }

    $permission = $this->interventionResourceManager->mutationPermission($command->interventionId, $command->userId);

    if (!$this->authorization->hasPermission($command->userId, $context->organizationId, $permission)) {
      throw new InterventionAccessDeniedException('Missing ' . $permission . ' permission.');
    }

    if (
      null !== $command->workItemId
      && !$this->interventionResourceManager->workItemBelongsToIntervention($command->workItemId, $command->interventionId)
    ) {
      throw new InterventionValidationException('Attachments can only reference work items from the same intervention.');
    }

    try {
      /** @var InterventionAttachmentId $attachmentId */
      $attachmentId = null === $command->attachmentId
        ? $this->uuidFactory->create(InterventionAttachmentId::class)
        : InterventionAttachmentId::fromString($command->attachmentId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    // A client-supplied id that already exists is a retry overwriting its own
    // row, not a new attachment — it must not be rejected at the cap.
    if (null === $this->attachmentRepository->findById($attachmentId)) {
      AttachmentConstraints::validateCount($this->attachmentRepository->countByInterventionId($command->interventionId));
    }

    $storagePath = StoragePathScheme::build(
      module: 'intervention',
      parentId: $command->interventionId,
      attachmentId: (string) $attachmentId,
      fileName: $command->fileName,
    );

    $attachment = InterventionAttachment::create(
      id: $attachmentId,
      interventionId: $command->interventionId,
      fileName: $command->fileName,
      storagePath: $storagePath,
      mimeType: $command->mimeType,
      size: $command->size,
      label: $command->label,
      workItemId: $command->workItemId,
    );

    $this->fileStorage->write($storagePath, $command->contents);

    try {
      $this->attachmentRepository->save($attachment);
    } catch (Throwable $dbException) {
      $this->fileStorage->delete($storagePath);

      throw $dbException;
    }

    return new AddInterventionAttachmentResult(
      attachmentId: (string) $attachment->id(),
      interventionId: $attachment->interventionId(),
      fileName: $attachment->fileName(),
      mimeType: $attachment->mimeType(),
      size: $attachment->size(),
      label: $attachment->label(),
      uploadedAt: $attachment->uploadedAt(),
      workItemId: $attachment->workItemId(),
    );
  }
  // #endregion
}
