<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\EditMessage;

use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingLinkRepositoryPort, MessagingMessageRepositoryPort, MessagingRealtimePublisherPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingNotificationService, MessagingSubjectResolverRegistry};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException, MessagingSubjectNotFoundException, MessagingValidationException};
use Messaging\Domain\Service\{MentionExtractor, UrlExtractor};
use Messaging\Domain\ValueObject\{ConversationVisibility, MessageReference, MessagingSubjectType};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\LoggerPort;
use Throwable;

use function sprintf;

/**
 * UseCase EditMessageHandler.
 *
 * Author-only: edits the body (re-validating and re-extracting mentions),
 * exposing `editedAt`. Only newly-added mentions (not already present before
 * the edit) trigger a notification. The write gate branches on the owning
 * conversation's visibility: a v1 subject thread requires the subject's own
 * write permission, a v2 channel requires participant membership. The
 * owning organization is derived from the loaded message, not supplied by
 * the caller.
 *
 * B3 (structured references): when `command->references` is non-null, it is
 * shape-validated via {@see MessageReference::listFromArray()} and
 * org-scoped EXISTENCE-validated per reference through the
 * `messaging.subject_resolver` seam — byte-for-byte the same validation
 * `PostMessageHandler` performs — before replacing the message's reference
 * set. A `null` value leaves existing references untouched.
 *
 * B2 (conversation links): after saving, re-extracts every `http(s)` URL
 * from the NEW body and REPLACES the message's link set via
 * {@see MessagingLinkRepositoryPort::replaceForMessage()} — an edit always
 * fully replaces the prior extraction, mirroring `body`'s own full-replace
 * contract.
 *
 * @category UseCase
 *
 * @version 1.3.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EditMessageHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingMessageRepositoryPort $messages the message repository port
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingLinkRepositoryPort $links the link repository port (B2)
   * @param MessagingSubjectResolverRegistry $resolvers the subject resolver registry
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   * @param MessagingNotificationService $notifications the mention notification service
   * @param MessagingRealtimePublisherPort $realtime the realtime publisher port
   * @param MentionExtractor $mentionExtractor the mention extractor
   * @param UrlExtractor $urlExtractor the url extractor (B2)
   * @param LoggerPort $logger the logger value
   */
  public function __construct(
    private MessagingMessageRepositoryPort $messages,
    private MessagingConversationRepositoryPort $conversations,
    private MessagingLinkRepositoryPort $links,
    private MessagingSubjectResolverRegistry $resolvers,
    private MessagingAccessPolicy $accessPolicy,
    private MessagingNotificationService $notifications,
    private MessagingRealtimePublisherPort $realtime,
    private MentionExtractor $mentionExtractor,
    private UrlExtractor $urlExtractor,
    private LoggerPort $logger,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param EditMessageCommand $command the command value
   *
   * @return EditMessageResult the use case result
   */
  public function __invoke(EditMessageCommand $command): EditMessageResult
  {
    $message = $this->messages->findAggregateById($command->messageId);
    if (null === $message) {
      throw MessagingNotFoundException::message($command->messageId);
    }

    $organizationId = $message->organizationId();
    $actorMemberId = $this->accessPolicy->resolveActiveMemberId($organizationId, $command->userId);
    if (!$message->isAuthoredBy($actorMemberId)) {
      throw new MessagingAccessDeniedException('Only the message author can edit it.');
    }

    $conversation = $this->conversations->findAggregateById($message->conversationId());
    if (null === $conversation) {
      throw MessagingNotFoundException::conversation($message->conversationId());
    }

    $subjectId = $conversation->subjectId();
    if (ConversationVisibility::PARTICIPANTS === $conversation->visibility()) {
      $this->accessPolicy->assertCanWriteChannel($command->userId, $organizationId, $message->conversationId(), $actorMemberId);
    } else {
      $requiredSubjectPermission = 'organization.messaging.write';
      if (null !== $subjectId) {
        $resolution = $this->resolvers->resolve($conversation->subjectType(), $organizationId, $subjectId);
        $requiredSubjectPermission = $resolution->requiredReadPermission;
      }
      $this->accessPolicy->assertCanWrite($command->userId, $organizationId, $requiredSubjectPermission);
    }

    $references = null;
    if (null !== $command->references) {
      $references = MessageReference::listFromArray($command->references);
      foreach ($references as $reference) {
        $this->assertReferenceExists($organizationId, $reference);
      }
    }

    $newlyMentioned = $message->edit($command->body, $this->mentionExtractor, $references);
    $view = $this->messages->save($message);

    $urls = $this->urlExtractor->extract($view->body);
    $this->links->replaceForMessage($view->id, $message->conversationId(), $urls, $view->updatedAt);

    try {
      $this->realtime->publishMessage($organizationId, $message->conversationId(), [
        'type' => 'message.updated',
        'messageId' => $view->id,
        'body' => $view->body,
        'references' => $view->references,
        'editedAt' => $view->editedAt?->format('c'),
      ]);
    } catch (Throwable $exception) {
      $this->logger->warning('Messaging realtime publish failed.', [
        'conversationId' => $message->conversationId(),
        'error' => $exception->getMessage(),
      ]);
    }

    foreach ($newlyMentioned as $mentionedMemberId) {
      if ($mentionedMemberId === $actorMemberId) {
        continue;
      }

      $this->notifications->mentioned(
        $organizationId,
        $message->conversationId(),
        $conversation->subjectType()->value,
        $subjectId,
        $mentionedMemberId,
      );
    }

    return new EditMessageResult($view, $actorMemberId);
  }

  /**
   * Method assertReferenceExists.
   *
   * Mirrors `PostMessageHandler::assertReferenceExists()` byte for byte —
   * see there for the rationale.
   *
   * @since 1.3.0
   *
   * @param string $organizationId the requesting organization identifier
   * @param MessageReference $reference the reference to validate
   */
  private function assertReferenceExists(string $organizationId, MessageReference $reference): void
  {
    try {
      $this->resolvers->resolve(MessagingSubjectType::from($reference->type), $organizationId, $reference->id);
    } catch (MessagingSubjectNotFoundException $exception) {
      throw new MessagingValidationException(
        sprintf('Reference "%s" of type "%s" was not found in this organization.', $reference->id, $reference->type),
        previous: $exception,
      );
    }
  }
}
