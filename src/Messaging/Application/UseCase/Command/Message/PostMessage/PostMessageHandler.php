<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\PostMessage;

use Messaging\Application\Port\Outbound\Internal\{MessagingConversationRepositoryPort, MessagingMessageRepositoryPort};
use Messaging\Application\Port\Outbound\{MessagingLinkRepositoryPort, MessagingParticipantRepositoryPort, MessagingRealtimePublisherPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingNotificationService, MessagingSubjectResolverRegistry};
use Messaging\Domain\Exception\{
  MessagingClientMessageAlreadyExistsException,
  MessagingNotFoundException,
  MessagingSubjectNotFoundException,
  MessagingValidationException
};
use Messaging\Domain\Model\Message\{Message, MessageCreationLinks};
use Messaging\Domain\Service\{MentionExtractor, UrlExtractor};
use Messaging\Domain\ValueObject\{ConversationVisibility, MessageId, MessageReference, MessagingSubjectType};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyExecutionPort;
use Shared\Application\Port\Outbound\{LoggerPort, RootTransactionManagerPort};
use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Shared\Domain\Exception\InvalidValueException;
use Throwable;

use function sprintf;

/**
 * UseCase PostMessageHandler.
 *
 * Posts a message: asserts the conversation is not archived, resolves and
 * gates by the subject's own write context (a v1 subject thread) or
 * participant membership (a v2 channel), persists the message and
 * atomically bumps the conversation counters, then best-effort publishes a
 * real-time update and fans out notifications — mentions for both, plus a
 * per-participant new-message notification for a channel (author excluded).
 * Realtime publishing and notifications never fail the post itself.
 *
 * B3 (structured references): shape-validates `command->references` via
 * {@see MessageReference::listFromArray()}, then org-scoped
 * EXISTENCE-validates each one through the EXISTING
 * `messaging.subject_resolver` seam ({@see MessagingSubjectResolverRegistry})
 * — the same cheap existence-check port already used to resolve the
 * conversation's own subject, reused rather than duplicated. Both
 * validations run BEFORE the message is persisted (fail closed).
 *
 * B2 (conversation links): after persisting, extracts every `http(s)` URL
 * from the message body via {@see UrlExtractor} and replaces the message's
 * link set via {@see MessagingLinkRepositoryPort::replaceForMessage()} — a
 * direct, synchronous call (mirrors how mention extraction is handled
 * inline in this same handler, not through a domain event/subscriber).
 *
 * @category UseCase
 * @version 1.3.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PostMessageHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingMessageRepositoryPort $messages the message repository port
   * @param MessagingParticipantRepositoryPort $participants the participant repository port
   * @param MessagingLinkRepositoryPort $links the link repository port (B2)
   * @param MessagingSubjectResolverRegistry $resolvers the subject resolver registry
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   * @param MessagingNotificationService $notifications the notification service
   * @param MessagingRealtimePublisherPort $realtime the realtime publisher port
   * @param MentionExtractor $mentionExtractor the mention extractor
   * @param UrlExtractor $urlExtractor the url extractor (B2)
   * @param UuidGeneratorPort $uuidFactory the uuid factory value
   * @param LoggerPort $logger the logger value
   * @param RootTransactionManagerPort $transactions commits the message, counters and links together
   */
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingMessageRepositoryPort $messages,
    private MessagingParticipantRepositoryPort $participants,
    private MessagingLinkRepositoryPort $links,
    private MessagingSubjectResolverRegistry $resolvers,
    private MessagingAccessPolicy $accessPolicy,
    private MessagingNotificationService $notifications,
    private MessagingRealtimePublisherPort $realtime,
    private MentionExtractor $mentionExtractor,
    private UrlExtractor $urlExtractor,
    private UuidGeneratorPort $uuidFactory,
    private LoggerPort $logger,
    private RootTransactionManagerPort $transactions,
    private ?HttpIdempotencyExecutionPort $idempotency = null,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param PostMessageCommand $command the command value
   *
   * @return PostMessageResult the use case result
   */
  public function __invoke(PostMessageCommand $command): PostMessageResult
  {
    $conversation = $this->conversations->findAggregateById($command->conversationId);
    if (null === $conversation) {
      throw MessagingNotFoundException::conversation($command->conversationId);
    }

    if ($conversation->isArchived()) {
      throw new MessagingValidationException('Cannot post a message to an archived conversation.');
    }

    $organizationId = $conversation->organizationId();
    $subjectId = $conversation->subjectId();
    $isChannel = ConversationVisibility::PARTICIPANTS === $conversation->visibility();

    $authorMemberId = $this->accessPolicy->resolveActiveMemberId($organizationId, $command->userId);

    if ($isChannel) {
      $this->accessPolicy->assertCanWriteChannel($command->userId, $organizationId, $command->conversationId, $authorMemberId);
    } else {
      $requiredSubjectPermission = 'organization.messaging.write';
      if (null !== $subjectId) {
        $resolution = $this->resolvers->resolve($conversation->subjectType(), $organizationId, $subjectId);
        $requiredSubjectPermission = $resolution->requiredReadPermission;
      }
      $this->accessPolicy->assertCanWrite($command->userId, $organizationId, $requiredSubjectPermission);
    }

    $references = MessageReference::listFromArray($command->references);
    foreach ($references as $reference) {
      $this->assertReferenceExists($organizationId, $reference);
    }

    $message = Message::create(
      id: $this->resolveMessageId($command->clientId),
      conversationId: $command->conversationId,
      organizationId: $organizationId,
      authorMemberId: $authorMemberId,
      rawBody: $command->body,
      mentionExtractor: $this->mentionExtractor,
      links: new MessageCreationLinks(references: $references),
    );

    [$result, $participantMemberIds] = $this->transactions->transactional(function () use ($message, $command, $isChannel, $authorMemberId) {
      $view = $this->messages->append($message);
      $this->conversations->touchOnNewMessage($command->conversationId, $view->createdAt);
      $urls = $this->urlExtractor->extract($view->body);
      $this->links->replaceForMessage($view->id, $command->conversationId, $urls, $view->createdAt);

      // Capture recipients before commit so a failed lookup also rolls back the write.
      $participantMemberIds = $isChannel ? $this->participants->listMemberIds($command->conversationId) : [];

      $result = new PostMessageResult($view, $authorMemberId);
      $this->idempotency?->captureResult($result);

      return [$result, $participantMemberIds];
    });
    $view = $result->message;

    try {
      $this->realtime->publishMessage($organizationId, $command->conversationId, [
        'type' => 'message.created',
        'messageId' => $view->id,
        'authorMemberId' => $view->authorMemberId,
        'body' => $view->body,
        'references' => $view->references,
        'createdAt' => $view->createdAt->format('c'),
      ]);
    } catch (Throwable $exception) {
      $this->logRealtimeFailure($command->conversationId, $exception);
    }

    if ($isChannel) {
      $this->notifications->channelMessagePosted($organizationId, $command->conversationId, $participantMemberIds, $authorMemberId);
    }

    foreach ($message->mentions() as $mentionedMemberId) {
      if ($mentionedMemberId === $authorMemberId) {
        continue;
      }

      $this->notifications->mentioned(
        $organizationId,
        $command->conversationId,
        $conversation->subjectType()->value,
        $subjectId,
        $mentionedMemberId,
      );
    }

    return $result;
  }

  /**
   * Method logRealtimeFailure.
   *
   * A diagnostic failure cannot invalidate a committed message or skip notifications.
   *
   * @param string $conversationId the conversation identifier
   * @param Throwable $failure the publication failure
   */
  private function logRealtimeFailure(string $conversationId, Throwable $failure): void
  {
    try {
      $this->logger->warning('Messaging realtime publish failed.', [
        'conversationId' => $conversationId,
        'error' => $failure->getMessage(),
      ]);
    } catch (Throwable) {
      // Diagnostics are best-effort after the durable write.
    }
  }

  /**
   * Method resolveMessageId.
   *
   * Uses the client-minted id when the caller supplied one, otherwise mints a
   * server-side id.
   *
   * A client id makes the write idempotent, which is what an offline outbox
   * needs: a queued send whose response was lost is retried with the same id
   * and conflicts instead of creating a second message. The check is a plain
   * read-then-write guarded by the primary key — a concurrent double-submit
   * of the same id would still collide on the primary key, which is the
   * database enforcing the same rule.
   *
   * @since 1.4.0
   *
   * @param ?string $clientId the client-minted message id, or null
   *
   * @return MessageId the identifier the message will carry
   */
  private function resolveMessageId(?string $clientId): MessageId
  {
    if (null === $clientId || '' === $clientId) {
      return new MessageId($this->uuidFactory->generate());
    }

    try {
      $messageId = MessageId::fromString($clientId);
    } catch (InvalidValueException $exception) {
      throw new MessagingValidationException('The client message identifier must be a valid UUID.', 0, $exception);
    }

    if (null !== $this->messages->findById($clientId)) {
      throw MessagingClientMessageAlreadyExistsException::forClientId($clientId);
    }

    return $messageId;
  }

  /**
   * Method assertReferenceExists.
   *
   * Org-scoped existence check for a single structured reference (B3),
   * reusing the `messaging.subject_resolver` seam — the same cheap
   * existence-check port pattern already used to resolve a conversation's
   * own subject. A resolver "not found" result is remapped from
   * `MessagingSubjectNotFoundException` (404, the wrong contract for an
   * optional attached reference) to `MessagingValidationException` (422):
   * an unresolvable reference is a bad REQUEST, not a missing RESOURCE.
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
