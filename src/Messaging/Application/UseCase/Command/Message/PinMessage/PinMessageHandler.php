<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\PinMessage;

use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMessageRepositoryPort, MessagingRealtimePublisherPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Domain\Exception\{MessagingNotFoundException, MessagingValidationException};
use Messaging\Domain\ValueObject\{ConversationVisibility, MessagingSubjectType};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\LoggerPort;
use Throwable;

/**
 * UseCase PinMessageHandler.
 *
 * Pins a message in its conversation — a property of the CONVERSATION,
 * visible to everyone who can read it (never a per-member "saved" flag).
 * Establishing a pin is gated exactly like posting a message: the acting
 * member needs `organization.messaging.write` plus the subject's own read
 * permission (subject threads), or channel participant write access
 * (channels) — a read-only member cannot alter the shared pinned set.
 * Idempotent: pinning an already-pinned message is a no-op. Refuses to pin
 * an already-tombstoned message. The owning organization is derived from the
 * loaded message, not supplied by the caller.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PinMessageHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.1.0
   *
   * @param MessagingMessageRepositoryPort $messages the message repository port
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingSubjectResolverRegistry $resolvers the subject resolver registry
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   * @param MessagingRealtimePublisherPort $realtime the realtime publisher port
   * @param LoggerPort $logger the logger value
   */
  public function __construct(
    private MessagingMessageRepositoryPort $messages,
    private MessagingConversationRepositoryPort $conversations,
    private MessagingSubjectResolverRegistry $resolvers,
    private MessagingAccessPolicy $accessPolicy,
    private MessagingRealtimePublisherPort $realtime,
    private LoggerPort $logger,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.1.0
   *
   * @param PinMessageCommand $command the command value
   *
   * @return PinMessageResult the use case result
   */
  public function __invoke(PinMessageCommand $command): PinMessageResult
  {
    $message = $this->messages->findAggregateById($command->messageId);
    if (null === $message) {
      throw MessagingNotFoundException::message($command->messageId);
    }

    if ($message->isDeleted()) {
      throw new MessagingValidationException('A deleted message cannot be pinned.');
    }

    $organizationId = $message->organizationId();
    $conversationId = $message->conversationId();
    $actorMemberId = $this->accessPolicy->resolveActiveMemberId($organizationId, $command->userId);

    $conversation = $this->conversations->findById($conversationId);
    if (null === $conversation) {
      throw MessagingNotFoundException::conversation($conversationId);
    }

    if (ConversationVisibility::PARTICIPANTS->value === $conversation->visibility) {
      $this->accessPolicy->assertCanWriteChannel($command->userId, $organizationId, $conversationId, $actorMemberId);
    } else {
      $requiredSubjectPermission = 'organization.messaging.write';
      if (null !== $conversation->subjectId) {
        $subjectType = MessagingSubjectType::from($conversation->subjectType);
        $resolution = $this->resolvers->resolve($subjectType, $organizationId, $conversation->subjectId);
        $requiredSubjectPermission = $resolution->requiredReadPermission;
      }
      $this->accessPolicy->assertCanWrite($command->userId, $organizationId, $requiredSubjectPermission);
    }

    $message->pin($actorMemberId);
    $view = $this->messages->save($message);

    try {
      $this->realtime->publishMessage($organizationId, $conversationId, [
        'type' => 'message.pinned',
        'messageId' => $command->messageId,
        'pinnedBy' => $actorMemberId,
      ]);
    } catch (Throwable $exception) {
      $this->logger->warning('Messaging realtime publish failed.', [
        'conversationId' => $conversationId,
        'error' => $exception->getMessage(),
      ]);
    }

    return new PinMessageResult($view, $actorMemberId);
  }
}
