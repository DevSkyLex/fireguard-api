<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Attachment\AddInterventionAttachment;

use Intervention\Application\Port\Outbound\InterventionAttachmentRepositoryPort;
use Intervention\Application\Service\InterventionResourceManager;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionConflictException, InterventionNotFoundException, InterventionValidationException};
use Intervention\Domain\Model\Attachment\InterventionAttachment;
use Intervention\Domain\ValueObject\{InterventionAttachmentId, InterventionAttachmentKind};
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Attachment\{AttachmentCategory, AttachmentConstraints, StoragePathScheme};
use Shared\Domain\Exception\InvalidValueException;
use Throwable;

use function in_array;
use function sprintf;

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
 * Phase 5d.2 adds the typed completion signature (`kind: signature`):
 * - allowed only while the intervention is `in_progress` or
 *   `changes_requested` — the statuses submission is made from — regardless
 *   of the generic mutability check above (409 otherwise);
 * - restricted to an image MIME type, rejecting a PDF-as-signature (422);
 * - at most one signature exists per intervention: a second signature
 *   upload REPLACES the first (old record + stored file removed in the same
 *   transaction) once the new one is durably saved — the traceability
 *   choice is that the signature reflects the FINAL submission, not a
 *   history of attempts;
 * - the replaced signature does not inflate the attachment cap: the count
 *   check is adjusted by the one row about to be removed, while the
 *   signature itself still counts toward `MAX_ATTACHMENTS_PER_PARENT` like
 *   any other attachment (kept simple — the cap is generous).
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

    $kind = InterventionAttachmentKind::tryFrom($command->kind);
    if (null === $kind) {
      throw new InterventionValidationException(sprintf('Unknown attachment kind "%s".', $command->kind));
    }

    if (InterventionAttachmentKind::SIGNATURE === $kind) {
      if (!in_array($context->status, ['in_progress', 'changes_requested'], true)) {
        throw new InterventionConflictException('The completion signature can only be uploaded while the intervention is in progress or changes are requested.');
      }
      if (!in_array($command->mimeType, AttachmentCategory::IMAGE->allowedMimeTypes(), true)) {
        throw new InterventionValidationException(sprintf('MIME type "%s" is not allowed for a signature attachment.', $command->mimeType));
      }
    }

    try {
      /** @var InterventionAttachmentId $attachmentId */
      $attachmentId = null === $command->attachmentId
        ? $this->uuidFactory->create(InterventionAttachmentId::class)
        : InterventionAttachmentId::fromString($command->attachmentId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    // The signature about to be replaced (see class docblock). Resolved
    // before the count-cap check so the replaced row does not count twice
    // against the cap, and before the write so a failed save leaves the
    // previous signature untouched.
    $previousSignature = InterventionAttachmentKind::SIGNATURE === $kind
      ? $this->attachmentRepository->findSignatureByInterventionId($command->interventionId)
      : null;

    // A client-supplied id that already exists is a retry overwriting its own
    // row, not a new attachment — it must not be rejected at the cap.
    if (null === $this->attachmentRepository->findById($attachmentId)) {
      $currentCount = $this->attachmentRepository->countByInterventionId($command->interventionId);
      if (null !== $previousSignature) {
        --$currentCount;
      }
      AttachmentConstraints::validateCount($currentCount);
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
      kind: $kind,
    );

    $this->fileStorage->write($storagePath, $command->contents);

    try {
      $this->attachmentRepository->save($attachment);
    } catch (Throwable $dbException) {
      $this->fileStorage->delete($storagePath);

      throw $dbException;
    }

    if (null !== $previousSignature && (string) $previousSignature->id() !== (string) $attachment->id()) {
      $this->attachmentRepository->delete($previousSignature->id());
      $this->fileStorage->delete($previousSignature->storagePath());
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
      kind: $attachment->kind()->value,
    );
  }
  // #endregion
}
