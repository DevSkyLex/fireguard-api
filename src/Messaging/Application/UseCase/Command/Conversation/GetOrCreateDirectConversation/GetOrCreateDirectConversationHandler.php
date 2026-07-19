<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Conversation\GetOrCreateDirectConversation;

use DateTimeImmutable;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort, MessagingReadMarkerRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Domain\Exception\MessagingValidationException;
use Messaging\Domain\Service\DirectConversationKey;
use Messaging\Domain\ValueObject\{ConversationVisibility, MessagingSubjectType};
use Shared\Application\Message\CommandHandler;

/**
 * UseCase GetOrCreateDirectConversationHandler.
 *
 * Idempotent get-or-create of a 1-to-1 direct conversation (L2.4) between
 * the acting member and another ACTIVE member of the same organization.
 * `subjectId` is the deterministic, order-independent pair key derived by
 * {@see DirectConversationKey} — sorting the two member ids before hashing
 * is what makes {@see MessagingConversationRepositoryPort::getOrCreate()}
 * resolve to the SAME conversation regardless of which member starts it
 * first, reusing its `UNIQUE (organization_id, subject_type, subject_id)`
 * race handling unchanged.
 *
 * Both members become real `messaging_participants` rows
 * (`source: 'manual'`) with a seeded read marker, exactly like
 * {@see \Messaging\Application\UseCase\Command\Channel\CreateChannel\CreateChannelHandler}
 * seeds its creator's — except BOTH sides are seeded here in one step,
 * since a direct conversation has no separate "join" step the way a
 * channel does. The seeding is guarded by the CALLER'S OWN participant row:
 * once a conversation already has the caller as a participant, a
 * subsequent `getOrCreate()` (the member re-opening the same DM) is a
 * pure no-op read, so a member's read marker is never silently reset to
 * "now" just for reopening an existing conversation.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrCreateDirectConversationHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingParticipantRepositoryPort $participants the participant repository port
   * @param MessagingReadMarkerRepositoryPort $readMarkers the read marker repository port
   * @param MessagingMemberDirectoryPort $members the messaging member directory port
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   */
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingParticipantRepositoryPort $participants,
    private MessagingReadMarkerRepositoryPort $readMarkers,
    private MessagingMemberDirectoryPort $members,
    private MessagingAccessPolicy $accessPolicy,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GetOrCreateDirectConversationCommand $command the command value
   *
   * @return GetOrCreateDirectConversationResult the use case result
   */
  public function __invoke(GetOrCreateDirectConversationCommand $command): GetOrCreateDirectConversationResult
  {
    $this->accessPolicy->assertCanUseMessaging($command->userId, $command->organizationId);

    $callerMemberId = $this->accessPolicy->resolveActiveMemberId($command->organizationId, $command->userId);

    if ($callerMemberId === $command->otherMemberId) {
      throw new MessagingValidationException('Cannot start a direct conversation with yourself.');
    }

    if (!$this->members->memberIsActive($command->organizationId, $command->otherMemberId)) {
      throw new MessagingValidationException('The target member is not active in this organization.');
    }

    $subjectId = DirectConversationKey::for($callerMemberId, $command->otherMemberId);

    $view = $this->conversations->getOrCreate(
      $command->organizationId,
      MessagingSubjectType::DIRECT,
      $subjectId,
      ConversationVisibility::PARTICIPANTS,
    );

    if (!$this->participants->isParticipant($view->id, $callerMemberId)) {
      $now = new DateTimeImmutable();
      $this->participants->addParticipant($view->id, $command->organizationId, $callerMemberId, null, 'manual', $now);
      $this->participants->addParticipant($view->id, $command->organizationId, $command->otherMemberId, null, 'manual', $now);
      $this->readMarkers->upsert($view->id, $command->organizationId, $callerMemberId, $now, null);
      $this->readMarkers->upsert($view->id, $command->organizationId, $command->otherMemberId, $now, null);
    }

    return new GetOrCreateDirectConversationResult($view);
  }
}
