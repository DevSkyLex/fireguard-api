<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Attachment\AddInterventionAttachment;

use Intervention\Application\Contract\Resource\InterventionAssignmentContext;
use Intervention\Application\Port\Outbound\InterventionAttachmentRepositoryPort;
use Intervention\Application\Service\InterventionResourceManager;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionConflictException, InterventionNotFoundException, InterventionValidationException};
use Intervention\Domain\Model\Attachment\{InterventionAttachment, InterventionAttachmentFile, InterventionAttachmentOptions};
use Intervention\Domain\ValueObject\{InterventionAttachmentId, InterventionAttachmentKind};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Attachment\{AttachmentCategory, AttachmentConstraints, StoragePathScheme};
use Shared\Domain\Exception\InvalidValueException;
use Throwable;

use function array_unique;
use function in_array;
use function sprintf;

/**
 * Class AddInterventionAttachmentHandler
 *
 * Adds intervention attachments with phase-based authorization and persisted-state capacity checks.
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
 *   upload REPLACES the first. Enforced in the database by the
 *   `uniq_intervention_attachment_signature` partial unique index
 *   (`(intervention_id) WHERE kind = 'signature'`, added in the Phase 5
 *   review) — which flips the save order from the generic path: the
 *   PREVIOUS record is deleted BEFORE the new one is persisted, both inside
 *   the SAME transaction (`InterventionAttachmentRepositoryPort::saveReplacingSignature()`),
 *   so a mid-write failure rolls back to the previous signature intact
 *   rather than leaving neither or both rows. Only the stored FILE for the
 *   replaced signature is removed afterward, once that transaction has
 *   committed — the traceability choice is that the signature reflects the
 *   FINAL submission, not a history of attempts;
 * - the replaced signature does not inflate the attachment cap: the count
 *   check is adjusted by the one row about to be removed, while the
 *   signature itself still counts toward `MAX_ATTACHMENTS_PER_PARENT` like
 *   any other attachment (kept simple — the cap is generous);
 * - a genuine concurrent duplicate (two uploads racing past the
 *   `findSignatureByInterventionId()` read before either commits) is still
 *   caught by the unique index and surfaces as `InterventionConflictException`
 *   (409), translated by the repository from the underlying
 *   `UniqueConstraintViolationException`.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddInterventionAttachmentHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies the intervention, authorization, storage and identifier capabilities for uploads.
   *
   * @access public
   *
   * @param InterventionResourceManager $interventionResourceManager reads intervention context and permissions
   * @param OrganizationAuthorizationPort $authorization checks membership and permission grants
   * @param InterventionAttachmentRepositoryPort $attachmentRepository reads and persists attachments
   * @param FileStoragePort $fileStorage writes and removes attachment files
   * @param UuidFactory $uuidFactory creates attachment identifiers when none is supplied
   *
   * @return void
   */
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
   * Method __invoke
   *
   * Authorizes an upload, stores its file and persists the attachment with signature replacement rules.
   *
   * @access public
   * @since 1.0.0
   *
   * @param AddInterventionAttachmentCommand $command upload data and requesting actor
   *
   * @return AddInterventionAttachmentResult persisted attachment details
   */
  public function __invoke(AddInterventionAttachmentCommand $command): AddInterventionAttachmentResult
  {
    try {
      /** @var InterventionAttachmentId $attachmentId */
      $attachmentId = null === $command->attachmentId
        ? $this->uuidFactory->create(InterventionAttachmentId::class)
        : InterventionAttachmentId::fromString($command->attachmentId);
    } catch (InvalidValueException $exception) {
      throw InvalidValueException::because($exception->getMessage(), $exception);
    }

    $storagePath = StoragePathScheme::build(
      module: 'intervention',
      parentId: $command->interventionId,
      attachmentId: (string) $attachmentId . '_' . $this->uuidFactory->generateRaw(),
      fileName: $command->fileName,
    );

    $obsoletePaths = [];

    try {
      $result = $this->attachmentRepository->withUploadLock(
        $command->interventionId,
        (string) $attachmentId,
        function () use ($command, $attachmentId, $storagePath, &$obsoletePaths): AddInterventionAttachmentResult {
          // The resource gateway takes a row lock, so its reads and all permission
          // checks must run inside the upload transaction rather than before it.
          $context = $this->interventionResourceManager->interventionContext($command->interventionId);
          if (null === $context) {
            throw InterventionNotFoundException::withId($command->interventionId);
          }
          $this->authorizeAttachment($command, $context);
          $kind = InterventionAttachmentKind::tryFrom($command->kind);
          if (null === $kind) {
            throw new InterventionValidationException(sprintf('Unknown attachment kind "%s".', $command->kind));
          }
          self::assertSignatureAllowed($command, $context, $kind);

          return $this->upload($command, $attachmentId, $kind, $storagePath, $obsoletePaths);
        },
      );
    } catch (Throwable $exception) {
      $this->fileStorage->delete($storagePath);

      throw $exception;
    }
    foreach (array_unique($obsoletePaths) as $obsoletePath) {
      $this->fileStorage->delete($obsoletePath);
    }

    return $result;
  }

  /**
   * Method upload
   *
   * Stores one attempt under the identity and parent locks; cleanup only owns this attempt's key.
   *
   * @access private
   *
   * @param AddInterventionAttachmentCommand $command authorized upload
   * @param InterventionAttachmentId $attachmentId serialized attachment identity
   * @param InterventionAttachmentKind $kind validated attachment kind
   * @param string $storagePath unique blob key owned by this attempt
   * @param list<string> $obsoletePaths blob keys to delete after commit
   *
   * @return AddInterventionAttachmentResult the committed upload summary
   */
  private function upload(AddInterventionAttachmentCommand $command, InterventionAttachmentId $attachmentId, InterventionAttachmentKind $kind, string $storagePath, array &$obsoletePaths): AddInterventionAttachmentResult
  {
    $existing = $this->attachmentRepository->findById($attachmentId);
    if (null !== $existing && $existing->interventionId() !== $command->interventionId) {
      throw new InterventionConflictException('The attachment identifier belongs to another intervention.');
    }
    if (null !== $existing) {
      return $this->result($existing);
    }

    $currentContext = $this->interventionResourceManager->interventionContext($command->interventionId);
    if (null === $currentContext) {
      throw InterventionNotFoundException::withId($command->interventionId);
    }
    $this->authorizeAttachment($command, $currentContext);
    self::assertSignatureAllowed($command, $currentContext, $kind);

    // The signature about to be replaced (see class docblock). Resolved
    // before the count-cap check so the replaced row does not count twice
    // against the cap, and before the write so a failed save leaves the
    // previous signature untouched.
    $previousSignature = InterventionAttachmentKind::SIGNATURE === $kind
      ? $this->attachmentRepository->findSignatureByInterventionId($command->interventionId)
      : null;

    $this->assertAttachmentCapacity($command->interventionId, $previousSignature);

    $attachment = InterventionAttachment::create(
      $attachmentId,
      $command->interventionId,
      new InterventionAttachmentFile($command->fileName, $storagePath, $command->mimeType, $command->size),
      new InterventionAttachmentOptions(label: $command->label, workItemId: $command->workItemId, kind: $kind),
    );

    $this->fileStorage->write($storagePath, $command->contents);

    if (InterventionAttachmentKind::SIGNATURE === $kind) {
      $this->attachmentRepository->saveReplacingSignature($attachment, $previousSignature?->id());
    } else {
      $this->attachmentRepository->save($attachment);
    }

    // Collect obsolete blobs here; the caller removes them only after commit.
    if (null !== $previousSignature && (string) $previousSignature->id() !== (string) $attachment->id()) {
      $obsoletePaths[] = $previousSignature->storagePath();
    }

    return $this->result($attachment);
  }

  /**
   * Method result
   *
   * Returns the persisted attachment snapshot for creation and identity replay.
   *
   * @access private
   *
   * @param InterventionAttachment $attachment persisted attachment
   *
   * @return AddInterventionAttachmentResult attachment summary
   */
  private function result(InterventionAttachment $attachment): AddInterventionAttachmentResult
  {
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

  /**
   * Method authorizeAttachment
   *
   * Checks organization membership, mutation permission and any referenced work item's ownership.
   *
   * @access private
   *
   * @param AddInterventionAttachmentCommand $command upload request and actor identifier
   * @param InterventionAssignmentContext $context intervention organization, status and assignment data
   *
   * @return void
   *
   * @throws InterventionNotFoundException when the actor is outside the intervention's organization
   * @throws InterventionAccessDeniedException when the actor lacks the derived mutation permission
   * @throws InterventionValidationException when the referenced work item belongs to another intervention
   */
  private function authorizeAttachment(AddInterventionAttachmentCommand $command, InterventionAssignmentContext $context): void
  {
    // Scope gate BEFORE mutationPermission(): its phase check can reveal
    // whether an intervention exists to a caller outside the organization.
    if (!$this->authorization->isMemberOf($command->userId, $context->organizationId)) {
      throw InterventionNotFoundException::withId($command->interventionId);
    }

    $permission = $this->interventionResourceManager->mutationPermission($command->interventionId, $command->userId);
    if (!$this->authorization->hasPermission($command->userId, $context->organizationId, $permission)) {
      throw new InterventionAccessDeniedException('Missing ' . $permission . ' permission.');
    }

    if (null !== $command->workItemId && !$this->interventionResourceManager->workItemBelongsToIntervention($command->workItemId, $command->interventionId)) {
      throw new InterventionValidationException('Attachments can only reference work items from the same intervention.');
    }
  }

  /**
   * Method assertSignatureAllowed
   *
   * Restricts completion signatures to submission phases and image MIME types.
   *
   * @access private
   *
   * @param AddInterventionAttachmentCommand $command upload metadata to validate
   * @param InterventionAssignmentContext $context intervention phase data
   * @param InterventionAttachmentKind $kind attachment kind being uploaded
   *
   * @return void
   *
   * @throws InterventionConflictException when the intervention is outside a signature phase
   * @throws InterventionValidationException when the signature MIME type is not an allowed image
   */
  private static function assertSignatureAllowed(AddInterventionAttachmentCommand $command, InterventionAssignmentContext $context, InterventionAttachmentKind $kind): void
  {
    if (InterventionAttachmentKind::SIGNATURE !== $kind) {
      return;
    }
    if (!in_array($context->status, ['in_progress', 'changes_requested'], true)) {
      throw new InterventionConflictException('The completion signature can only be uploaded while the intervention is in progress or changes are requested.');
    }
    if (!in_array($command->mimeType, AttachmentCategory::IMAGE->allowedMimeTypes(), true)) {
      throw new InterventionValidationException(sprintf('MIME type "%s" is not allowed for a signature attachment.', $command->mimeType));
    }
  }

  /**
   * Method assertAttachmentCapacity
   *
   * Checks the stored attachment count while accounting for retries and the signature being replaced.
   *
   * @access private
   *
   * @param string $interventionId intervention whose attachment count is checked
   * @param InterventionAttachment|null $previousSignature signature row that will be replaced, if any
   *
   * @return void
   */
  private function assertAttachmentCapacity(string $interventionId, ?InterventionAttachment $previousSignature): void
  {
    $currentCount = $this->attachmentRepository->countByInterventionId($interventionId);
    if (null !== $previousSignature) {
      --$currentCount;
    }
    AttachmentConstraints::validateCount($currentCount);
  }
  // #endregion
}
