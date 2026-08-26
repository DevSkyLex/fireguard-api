<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Attachment\DeleteInterventionAttachment;

use Intervention\Application\Port\Outbound\InterventionAttachmentRepositoryPort;
use Intervention\Application\Service\InterventionResourceManager;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionAttachmentNotFoundException, InterventionNotFoundException};
use Intervention\Domain\ValueObject\InterventionAttachmentId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase DeleteInterventionAttachmentHandler.
 *
 * Authoritative phase-based write authorization for attachment deletion —
 * see {@see \Intervention\Application\UseCase\Command\Attachment\AddInterventionAttachment\AddInterventionAttachmentHandler}.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteInterventionAttachmentHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private InterventionResourceManager $interventionResourceManager,
    private OrganizationAuthorizationPort $authorization,
    private InterventionAttachmentRepositoryPort $attachmentRepository,
    private FileStoragePort $fileStorage,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(DeleteInterventionAttachmentCommand $command): DeleteInterventionAttachmentResult
  {
    $context = $this->interventionResourceManager->interventionContext($command->interventionId);

    if (null === $context) {
      throw InterventionNotFoundException::withId($command->interventionId);
    }

    // Scope gate BEFORE the permission is derived — see the identical note in
    // AddInterventionAttachmentHandler: deriving the permission reads the
    // intervention's phase and can throw a conflict, which would confirm to
    // an outsider that this intervention exists.
    if (!$this->authorization->isMemberOf($command->userId, $context->organizationId)) {
      throw InterventionNotFoundException::withId($command->interventionId);
    }

    $permission = $this->interventionResourceManager->mutationPermission($command->interventionId, $command->userId);

    if (!$this->authorization->hasPermission($command->userId, $context->organizationId, $permission)) {
      throw new InterventionAccessDeniedException('Missing ' . $permission . ' permission.');
    }

    try {
      $attachmentId = InterventionAttachmentId::fromString($command->attachmentId);
    } catch (InvalidValueException $exception) {
      throw InvalidValueException::because($exception->getMessage(), $exception);
    }

    $attachment = $this->attachmentRepository->findById($attachmentId);

    if (null === $attachment || $attachment->interventionId() !== $command->interventionId) {
      throw InterventionAttachmentNotFoundException::withId($command->attachmentId);
    }

    $this->attachmentRepository->delete($attachmentId);
    $this->fileStorage->delete($attachment->storagePath());

    return new DeleteInterventionAttachmentResult(
      attachmentId: (string) $attachmentId,
      interventionId: $command->interventionId,
    );
  }
  // #endregion
}
