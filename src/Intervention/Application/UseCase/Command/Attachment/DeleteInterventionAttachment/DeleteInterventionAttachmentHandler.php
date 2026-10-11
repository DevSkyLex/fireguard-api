<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Attachment\DeleteInterventionAttachment;

use Intervention\Application\Port\Outbound\Internal\InterventionAttachmentRepositoryPort;
use Intervention\Application\Port\Outbound\InterventionMediaLookupPort;
use Intervention\Application\Service\InterventionResourceManager;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionAttachmentNotFoundException, InterventionNotFoundException};
use Intervention\Domain\Exception\InterventionPreconditionFailedException;
use Intervention\Domain\ValueObject\InterventionAttachmentId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{FileCleanupPort, RootTransactionManagerPort};
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase DeleteInterventionAttachmentHandler.
 *
 * Authoritative phase-based write authorization for attachment deletion —
 * see {@see \Intervention\Application\UseCase\Command\Attachment\AddInterventionAttachment\AddInterventionAttachmentHandler}.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteInterventionAttachmentHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Checks intervention scope and permission before deleting attachment data.
   *
   * @access public
   *
   * @param InterventionResourceManager $interventionResourceManager service resolving intervention context and mutation permissions
   * @param OrganizationAuthorizationPort $authorization port used to verify organization membership and permissions
   * @param InterventionAttachmentRepositoryPort $attachmentRepository repository used to locate and remove attachment records
   * @param FileCleanupPort $fileCleanup durably removes bytes after commit
   *
   * @return void
   */
  public function __construct(
    private InterventionResourceManager $interventionResourceManager,
    private OrganizationAuthorizationPort $authorization,
    private InterventionAttachmentRepositoryPort $attachmentRepository,
    private FileCleanupPort $fileCleanup,
    private RootTransactionManagerPort $transactions,
    private InterventionMediaLookupPort $media,
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
    return $this->transactions->transactional(function () use ($command): DeleteInterventionAttachmentResult {
      $this->authorizeDeletion($command);
      $attachmentId = $this->attachmentId($command->attachmentId);
      $attachment = $this->attachmentRepository->findById($attachmentId);

      if (null === $attachment || $attachment->interventionId() !== $command->interventionId) {
        throw InterventionAttachmentNotFoundException::withId($command->attachmentId);
      }

      $this->assertLockedRevision($command);
      $this->attachmentRepository->delete($attachmentId);
      $this->fileCleanup->request($attachment->storagePath());
      $this->interventionResourceManager->touchDraftIntervention($command->interventionId);

      return new DeleteInterventionAttachmentResult(
        attachmentId: (string) $attachmentId,
        interventionId: $command->interventionId,
      );
    });
  }

  /**
   * Method authorizeDeletion
   *
   * Denies outsiders before phase-derived permission checks can reveal parent state.
   *
   * @access private
   *
   * @param DeleteInterventionAttachmentCommand $command parent scope and actor
   *
   * @return void
   */
  private function authorizeDeletion(DeleteInterventionAttachmentCommand $command): void
  {
    $context = $this->interventionResourceManager->interventionContext($command->interventionId);
    if (null === $context || !$this->authorization->isMemberOf($command->userId, $context->organizationId)) {
      throw InterventionNotFoundException::withId($command->interventionId);
    }
    $permission = $this->interventionResourceManager->mutationPermission($command->interventionId, $command->userId);
    if (!$this->authorization->hasPermission($command->userId, $context->organizationId, $permission)) {
      throw new InterventionAccessDeniedException('Missing ' . $permission . ' permission.');
    }
  }

  /**
   * Method attachmentId
   *
   * Validates attachment identity after the parent authorization guards.
   *
   * @access private
   *
   * @param string $value requested attachment identifier
   *
   * @return InterventionAttachmentId validated identity
   */
  private function attachmentId(string $value): InterventionAttachmentId
  {
    try {
      return InterventionAttachmentId::fromString($value);
    } catch (InvalidValueException $exception) {
      throw InvalidValueException::because($exception->getMessage(), $exception);
    }
  }

  /**
   * Method assertLockedRevision
   *
   * Locks the stored row before checking the optional revision and mutating SQL state.
   *
   * @access private
   *
   * @param DeleteInterventionAttachmentCommand $command attachment identity and expected revision
   *
   * @return void
   */
  private function assertLockedRevision(DeleteInterventionAttachmentCommand $command): void
  {
    $stored = $this->media->attachment($command->attachmentId, lock: true);
    if (null === $stored) {
      throw new InterventionAttachmentNotFoundException('Attachment not found.');
    }
    if (null !== $command->expectedRevision && $stored->revision !== $command->expectedRevision) {
      throw new InterventionPreconditionFailedException('The resource revision is stale.');
    }
  }
  // #endregion
}
