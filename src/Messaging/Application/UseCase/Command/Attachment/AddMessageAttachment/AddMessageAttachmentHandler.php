<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Attachment\AddMessageAttachment;

use Messaging\Application\Port\Outbound\Internal\{MessagingAttachmentRepositoryPort, MessagingConversationRepositoryPort, MessagingMessageRepositoryPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry, MessagingUploadAttempt};
use Messaging\Domain\Exception\{MessagingNotFoundException, MessagingValidationException};
use Messaging\Domain\Model\Attachment\{MessagingAttachment, MessagingAttachmentFile};
use Messaging\Domain\ValueObject\{ConversationVisibility, MessagingAttachmentId, MessagingSubjectType};
use Shared\Application\Contract\Transaction\CommitOutcomeUnknownException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{FileCleanupPort, FileStoragePort, LoggerPort, RootTransactionManagerPort};
use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Shared\Domain\Attachment\{AttachmentConstraints, StoragePathScheme};
use Shared\Domain\Exception\InvalidValueException;
use Throwable;

/**
 * UseCase AddMessageAttachmentHandler.
 *
 * Uploads a file onto an existing message: resolves the owning conversation
 * to gate by the subject's own write context (a v1 subject thread) or
 * participant membership (a v2 channel) — mirroring `PostMessageHandler` —
 * then writes the file BEFORE persisting the attachment record, rolling the
 * write back on a database failure (mirrors `AddInspectionAttachmentHandler`).
 * Refuses to attach a file to an already-tombstoned message.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddMessageAttachmentHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Validates message and conversation access before storing attachment metadata and bytes.
   *
   * @access public
   *
   * @param MessagingMessageRepositoryPort $messages repository used to load the target message
   * @param MessagingConversationRepositoryPort $conversations repository used to load its conversation
   * @param MessagingSubjectResolverRegistry $resolvers registry resolving permissions for subject conversations
   * @param MessagingAccessPolicy $accessPolicy policy enforcing organization and participant access
   * @param MessagingAttachmentRepositoryPort $attachments repository used to persist attachment metadata
   * @param FileStoragePort $fileStorage storage port used to write or roll back file bytes
   * @param UuidGeneratorPort $uuidFactory generates attachment and attempt identifiers
   * @param RootTransactionManagerPort $transactions owns the final main commit
   * @param FileCleanupPort $cleanup schedules failed compensation durably
   * @param LoggerPort $logger records cleanup failures without masking persistence errors
   *
   * @return void
   */
  public function __construct(
    private MessagingMessageRepositoryPort $messages,
    private MessagingConversationRepositoryPort $conversations,
    private MessagingSubjectResolverRegistry $resolvers,
    private MessagingAccessPolicy $accessPolicy,
    private MessagingAttachmentRepositoryPort $attachments,
    private FileStoragePort $fileStorage,
    private UuidGeneratorPort $uuidFactory,
    private RootTransactionManagerPort $transactions,
    private FileCleanupPort $cleanup,
    private LoggerPort $logger,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(AddMessageAttachmentCommand $command): AddMessageAttachmentResult
  {
    $this->transactions->assertCanStart();
    $attempt = new MessagingUploadAttempt();

    try {
      return $this->transactions->transactional(function () use ($command, $attempt): AddMessageAttachmentResult {
        return $this->attachments->withMessageLock(
          $command->messageId,
          function () use ($command, $attempt): AddMessageAttachmentResult {
            return $this->upload($command, $attempt);
          },
        );
      });
    } catch (Throwable $exception) {
      $writtenPath = $attempt->storagePath();
      if (null !== $writtenPath && !$exception instanceof CommitOutcomeUnknownException) {
        try {
          $this->fileStorage->delete($writtenPath);
        } catch (Throwable $cleanupFailure) {
          $this->logCleanupFailure('Messaging upload compensation failed.', $cleanupFailure);

          try {
            $this->transactions->transactional(fn () => $this->cleanup->request($writtenPath));
          } catch (Throwable $enqueueFailure) {
            $this->logCleanupFailure('Messaging upload cleanup enqueue failed.', $enqueueFailure);
          }
        }
      }

      throw $exception;
    }
  }

  /**
   * Method upload
   *
   * Checks identity and attachment limits while holding the message lock.
   *
   * @access private
   *
   * @param AddMessageAttachmentCommand $command upload request
   * @param MessagingUploadAttempt $attempt invocation-local storage write state
   *
   * @return AddMessageAttachmentResult persisted or replayed attachment
   */
  private function upload(AddMessageAttachmentCommand $command, MessagingUploadAttempt $attempt): AddMessageAttachmentResult
  {
    $message = $this->messages->findAggregateById($command->messageId);
    if (null === $message) {
      throw MessagingNotFoundException::message($command->messageId);
    }

    if ($message->isDeleted()) {
      throw new MessagingValidationException('Cannot attach a file to a deleted message.');
    }

    $organizationId = $message->organizationId();
    $conversationId = $message->conversationId();

    $conversation = $this->conversations->findById($conversationId);
    if (null === $conversation) {
      throw MessagingNotFoundException::conversation($conversationId);
    }

    $uploaderMemberId = $this->accessPolicy->resolveActiveMemberId($organizationId, $command->userId);

    if (ConversationVisibility::PARTICIPANTS->value === $conversation->visibility) {
      $this->accessPolicy->assertCanWriteChannel($command->userId, $organizationId, $conversationId, $uploaderMemberId);
    } else {
      $requiredSubjectPermission = 'organization.messaging.write';
      if (null !== $conversation->subjectId) {
        $subjectType = MessagingSubjectType::from($conversation->subjectType);
        $resolution = $this->resolvers->resolve($subjectType, $organizationId, $conversation->subjectId);
        $requiredSubjectPermission = $resolution->requiredReadPermission;
      }
      $this->accessPolicy->assertCanWrite($command->userId, $organizationId, $requiredSubjectPermission);
    }

    try {
      /** @var MessagingAttachmentId $attachmentId */
      $attachmentId = null === $command->attachmentId
        ? new MessagingAttachmentId($this->uuidFactory->generate())
        : MessagingAttachmentId::fromString($command->attachmentId);
    } catch (InvalidValueException $exception) {
      throw InvalidValueException::because($exception->getMessage(), $exception);
    }

    $existing = $this->attachments->findById($attachmentId);
    if (null !== $existing) {
      if ($existing->messageId() !== $command->messageId || $existing->organizationId() !== $organizationId) {
        throw new MessagingValidationException('The attachment identifier belongs to another message.');
      }

      return $this->result($existing, $this->attachments->findViewById((string) $attachmentId)->revision ?? 1);
    }
    AttachmentConstraints::validateCount($this->attachments->countByMessageId($command->messageId));

    $storagePath = StoragePathScheme::build(
      module: 'messaging',
      parentId: $conversationId,
      attachmentId: (string) $attachmentId . '_' . $this->uuidFactory->generate(),
      fileName: $command->fileName,
    );

    $attachment = MessagingAttachment::create(
      id: $attachmentId,
      messageId: $command->messageId,
      conversationId: $conversationId,
      organizationId: $organizationId,
      uploadedByMemberId: $uploaderMemberId,
      file: new MessagingAttachmentFile(
        fileName: $command->fileName,
        storagePath: $storagePath,
        mimeType: $command->mimeType,
        size: $command->size,
        label: $command->label,
      ),
    );

    $attempt->mark($storagePath);
    $this->fileStorage->write($storagePath, $command->contents);
    $this->attachments->save($attachment);

    return $this->result($attachment);
  }

  /**
   * Method result
   *
   * Copies persisted attachment metadata for the HTTP adapter.
   *
   * @access private
   *
   * @param MessagingAttachment $attachment persisted attachment
   * @param int $revision persisted concurrency revision
   *
   * @return AddMessageAttachmentResult upload result
   */
  private function result(MessagingAttachment $attachment, int $revision = 1): AddMessageAttachmentResult
  {

    return new AddMessageAttachmentResult(
      attachmentId: (string) $attachment->id(),
      messageId: $attachment->messageId(),
      conversationId: $attachment->conversationId(),
      organizationId: $attachment->organizationId(),
      uploadedByMemberId: $attachment->uploadedByMemberId(),
      fileName: $attachment->fileName(),
      mimeType: $attachment->mimeType(),
      size: $attachment->size(),
      label: $attachment->label(),
      uploadedAt: $attachment->uploadedAt(),
      revision: $revision,
    );
  }

  /**
   * Method logCleanupFailure
   *
   * Keeps a failed diagnostic delivery from replacing the original persistence failure.
   *
   * @access private
   *
   * @param string $message diagnostic context
   * @param Throwable $failure cleanup or enqueue failure
   *
   * @return void
   */
  private function logCleanupFailure(string $message, Throwable $failure): void
  {
    try {
      $this->logger->warning($message, ['error' => $failure->getMessage()]);
    } catch (Throwable) {
      // A diagnostic failure must not replace the original persistence failure or prevent durable cleanup.
    }
  }
  // #endregion
}
