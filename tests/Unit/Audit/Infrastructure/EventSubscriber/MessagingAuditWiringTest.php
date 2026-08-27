<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Infrastructure\EventSubscriber;

use Audit\Application\UseCase\Command\RecordAuditEvent\{RecordAuditEventCommand, RecordAuditEventResult};
use Audit\Infrastructure\EventSubscriber\AuditEventSubscriber;
use Audit\Infrastructure\Service\AuditPiiSanitizer;
use Messaging\Domain\Event\Channel\{
  MessagingChannelCreatedEvent,
  MessagingChannelParentChangedEvent,
  MessagingChannelParticipantAddedEvent,
  MessagingChannelParticipantRemovedEvent,
  MessagingChannelTeamBindingChangedEvent
};
use Messaging\Domain\Event\Conversation\MessagingConversationArchivedEvent;
use Messaging\Domain\Event\Message\{MessagingMessageModeratedEvent, MessagingMessageUnpinModeratedEvent};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Infrastructure\EventDispatcher\SymfonyEventDispatcherAdapter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;

use function array_keys;
use function count;
use function sprintf;

/**
 * Test MessagingAuditWiringTest.
 *
 * Wiring proof for the Messaging governance slice: every messaging
 * domain event, dispatched through the real event-name derivation of
 * SymfonyEventDispatcherAdapter, reaches AuditEventSubscriber and
 * produces the expected audit action, subject and metadata.
 *
 * @category Event Subscriber Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: AuditEventSubscriber::class)]
final class MessagingAuditWiringTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = 'org-1';

  private const string CONVERSATION_ID = 'conv-1';

  private const string ACTOR_USER_ID = 'user-1';
  // #endregion

  // #region Tests
  #[Test]
  public function testEveryMessagingDomainEventProducesItsAuditRecord(): void
  {
    $events = [
      new MessagingConversationArchivedEvent(
        organizationId: self::ORGANIZATION_ID,
        conversationId: self::CONVERSATION_ID,
        subjectType: 'facility',
        subjectId: 'fac-1',
        actorUserId: self::ACTOR_USER_ID,
      ),
      new MessagingMessageModeratedEvent(
        organizationId: self::ORGANIZATION_ID,
        conversationId: self::CONVERSATION_ID,
        messageId: 'msg-1',
        authorMemberId: 'member-1',
        actorUserId: self::ACTOR_USER_ID,
      ),
      new MessagingMessageUnpinModeratedEvent(
        organizationId: self::ORGANIZATION_ID,
        conversationId: self::CONVERSATION_ID,
        messageId: 'msg-1',
        pinnedByMemberId: 'member-2',
        actorUserId: self::ACTOR_USER_ID,
      ),
      new MessagingChannelCreatedEvent(
        organizationId: self::ORGANIZATION_ID,
        conversationId: self::CONVERSATION_ID,
        name: 'General',
        createdByMemberId: 'member-1',
        actorUserId: self::ACTOR_USER_ID,
      ),
      new MessagingChannelParticipantAddedEvent(
        organizationId: self::ORGANIZATION_ID,
        conversationId: self::CONVERSATION_ID,
        memberId: 'member-3',
        actorUserId: self::ACTOR_USER_ID,
      ),
      new MessagingChannelParticipantRemovedEvent(
        organizationId: self::ORGANIZATION_ID,
        conversationId: self::CONVERSATION_ID,
        memberId: 'member-3',
        actorUserId: self::ACTOR_USER_ID,
      ),
      new MessagingChannelTeamBindingChangedEvent(
        organizationId: self::ORGANIZATION_ID,
        conversationId: self::CONVERSATION_ID,
        teamId: 'team-1',
        actorUserId: self::ACTOR_USER_ID,
      ),
      new MessagingChannelParentChangedEvent(
        organizationId: self::ORGANIZATION_ID,
        conversationId: self::CONVERSATION_ID,
        parentConversationId: null,
        actorUserId: self::ACTOR_USER_ID,
      ),
    ];

    $expected = [
      'messaging.conversation_archived' => ['messaging_conversation', self::CONVERSATION_ID, ['subject_type' => 'facility', 'subject_id' => 'fac-1', 'organization_id' => self::ORGANIZATION_ID]],
      'messaging.message_moderated' => ['messaging_message', 'msg-1', ['conversation_id' => self::CONVERSATION_ID, 'author_member_id' => 'member-1', 'organization_id' => self::ORGANIZATION_ID]],
      'messaging.message_unpin_moderated' => ['messaging_message', 'msg-1', ['conversation_id' => self::CONVERSATION_ID, 'pinned_by_member_id' => 'member-2', 'organization_id' => self::ORGANIZATION_ID]],
      'messaging.channel_created' => ['messaging_conversation', self::CONVERSATION_ID, ['name' => 'General', 'created_by_member_id' => 'member-1', 'organization_id' => self::ORGANIZATION_ID]],
      'messaging.channel_participant_added' => ['messaging_conversation', self::CONVERSATION_ID, ['member_id' => 'member-3', 'organization_id' => self::ORGANIZATION_ID]],
      'messaging.channel_participant_removed' => ['messaging_conversation', self::CONVERSATION_ID, ['member_id' => 'member-3', 'organization_id' => self::ORGANIZATION_ID]],
      'messaging.channel_team_binding_changed' => ['messaging_conversation', self::CONVERSATION_ID, ['team_id' => 'team-1', 'organization_id' => self::ORGANIZATION_ID]],
      'messaging.channel_parent_changed' => ['messaging_conversation', self::CONVERSATION_ID, ['parent_conversation_id' => null, 'organization_id' => self::ORGANIZATION_ID]],
    ];

    /** @var list<RecordAuditEventCommand> $recorded */
    $recorded = [];
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willReturnCallback(static function (RecordAuditEventCommand $command) use (&$recorded): RecordAuditEventResult {
        $recorded[] = $command;

        return new RecordAuditEventResult(eventId: 'event-1');
      });

    $adapter = $this->dispatcherFor($commandBus);

    foreach ($events as $event) {
      $adapter->dispatch($event);
    }

    self::assertCount(count($expected), $recorded);

    $actions = [];
    foreach ($recorded as $command) {
      $actions[] = $command->action;
      [$subjectType, $subjectId, $metadata] = $expected[$command->action];
      self::assertSame($subjectType, $command->subjectType, sprintf('subjectType mismatch for %s', $command->action));
      self::assertSame($subjectId, $command->subjectId, sprintf('subjectId mismatch for %s', $command->action));
      self::assertSame($metadata, $command->metadata, sprintf('metadata mismatch for %s', $command->action));
      self::assertSame('user', $command->actorType, sprintf('actorType mismatch for %s', $command->action));
      self::assertSame(self::ACTOR_USER_ID, $command->actorId, sprintf('actor mismatch for %s', $command->action));
    }

    self::assertSame(array_keys($expected), $actions);
  }

  private function dispatcherFor(CommandBusPort $commandBus): SymfonyEventDispatcherAdapter
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: new AuditPiiSanitizer(includePii: true, piiSalt: 'salt-for-tests'),
      requestStack: new RequestStack(),
      security: $security,
      logger: new NullLogger(),
    );

    $symfonyDispatcher = new EventDispatcher();
    $symfonyDispatcher->addSubscriber($subscriber);

    return new SymfonyEventDispatcherAdapter(
      eventDispatcher: $symfonyDispatcher,
      logger: new NullLogger(),
    );
  }
  // #endregion
}
