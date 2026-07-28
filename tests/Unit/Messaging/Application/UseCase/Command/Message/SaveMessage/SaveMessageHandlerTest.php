<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Message\SaveMessage;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Contract\Subject\MessagingSubjectResolution;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingMessageRepositoryPort, MessagingParticipantRepositoryPort, MessagingSavedMessageRepositoryPort, MessagingSubjectResolverPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Application\UseCase\Command\Message\SaveMessage\{SaveMessageCommand, SaveMessageHandler};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException, MessagingValidationException};
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\ValueObject\{MessageId, MessagingSubjectType};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test SaveMessageHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SaveMessageHandler::class)]
final class SaveMessageHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655440000';

  private const string MESSAGE_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string AUTHOR_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string SAVING_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440003';

  #[Test]
  public function testInvokeSavesTheMessage(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->conversationView());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::SAVING_MEMBER_ID);

    /** @var MessagingSavedMessageRepositoryPort&MockObject $savedMessages */
    $savedMessages = $this->createMock(MessagingSavedMessageRepositoryPort::class);
    $savedMessages->expects(self::once())
      ->method('save')
      ->with(self::MESSAGE_ID, self::ORG_ID, self::SAVING_MEMBER_ID);

    $handler = new SaveMessageHandler(
      $messages,
      $conversations,
      $savedMessages,
      new MessagingSubjectResolverRegistry([$this->facilityResolver()]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $result = $handler->__invoke(new SaveMessageCommand('user-1', self::MESSAGE_ID));

    self::assertSame(self::MESSAGE_ID, $result->message->id);
    self::assertSame(self::SAVING_MEMBER_ID, $result->currentMemberId);
  }

  #[Test]
  public function testInvokeThrowsWhenMessageIsAlreadyDeleted(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message(deleted: true));

    $handler = new SaveMessageHandler(
      $messages,
      $this->createStub(MessagingConversationRepositoryPort::class),
      $this->createStub(MessagingSavedMessageRepositoryPort::class),
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $this->expectException(MessagingValidationException::class);

    $handler->__invoke(new SaveMessageCommand('user-1', self::MESSAGE_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenMessageIsNotFound(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn(null);

    $handler = new SaveMessageHandler(
      $messages,
      $this->createStub(MessagingConversationRepositoryPort::class),
      $this->createStub(MessagingSavedMessageRepositoryPort::class),
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new SaveMessageCommand('user-1', self::MESSAGE_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenReadPermissionIsMissing(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->conversationView());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::SAVING_MEMBER_ID);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')->willThrowException(new MessagingAccessDeniedException('Missing permission.'));

    $savedMessages = $this->createMock(MessagingSavedMessageRepositoryPort::class);
    $savedMessages->expects(self::never())->method('save');

    $handler = new SaveMessageHandler(
      $messages,
      $conversations,
      $savedMessages,
      new MessagingSubjectResolverRegistry([$this->facilityResolver()]),
      new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $this->expectException(MessagingAccessDeniedException::class);

    $handler->__invoke(new SaveMessageCommand('user-1', self::MESSAGE_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenTheConversationIsNotFound(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn(null);

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::SAVING_MEMBER_ID);

    $handler = new SaveMessageHandler(
      $messages,
      $conversations,
      $this->createStub(MessagingSavedMessageRepositoryPort::class),
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new SaveMessageCommand('user-1', self::MESSAGE_ID));
  }

  #[Test]
  public function testInvokeEnforcesChannelParticipationForAParticipantsConversation(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->channelView());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::SAVING_MEMBER_ID);

    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn(true);

    $savedMessages = $this->createMock(MessagingSavedMessageRepositoryPort::class);
    $savedMessages->expects(self::once())->method('save');
    $savedMessages->method('findSavedMessageIds')->willReturn([self::MESSAGE_ID]);

    $handler = new SaveMessageHandler(
      $messages,
      $conversations,
      $savedMessages,
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $participants),
    );

    $result = $handler->__invoke(new SaveMessageCommand('user-1', self::MESSAGE_ID));

    self::assertSame(self::MESSAGE_ID, $result->message->id);
  }

  #[Test]
  public function testInvokeRejectsANonParticipantOfAChannel(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->channelView());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::SAVING_MEMBER_ID);

    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn(false);

    $handler = new SaveMessageHandler(
      $messages,
      $conversations,
      $this->createStub(MessagingSavedMessageRepositoryPort::class),
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $participants),
    );

    $this->expectException(MessagingAccessDeniedException::class);

    $handler->__invoke(new SaveMessageCommand('user-1', self::MESSAGE_ID));
  }

  private function channelView(): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(self::CONVERSATION_ID, self::ORG_ID, 'channel', null, 'participants', null, 1, false, $now, $now, 'general');
  }

  private function facilityResolver(): MessagingSubjectResolverPort
  {
    $resolver = $this->createStub(MessagingSubjectResolverPort::class);
    $resolver->method('supports')->willReturnCallback(static fn (MessagingSubjectType $type): bool => MessagingSubjectType::FACILITY === $type);
    $resolver->method('resolve')->willReturn(new MessagingSubjectResolution(true, 'Site nord', 'organization.facilities.read'));

    return $resolver;
  }

  private function message(bool $deleted = false): Message
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return Message::reconstitute(
      MessageId::fromString(self::MESSAGE_ID),
      self::CONVERSATION_ID,
      self::ORG_ID,
      self::AUTHOR_MEMBER_ID,
      'Hello team',
      [],
      null,
      $deleted ? $now : null,
      $deleted ? self::AUTHOR_MEMBER_ID : null,
      $now,
      $now,
    );
  }

  private function conversationView(): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(self::CONVERSATION_ID, self::ORG_ID, 'facility', 'facility-1', 'subject', null, 1, false, $now, $now);
  }
}
