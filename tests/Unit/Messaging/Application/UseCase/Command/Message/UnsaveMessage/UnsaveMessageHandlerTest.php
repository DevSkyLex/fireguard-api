<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Message\UnsaveMessage;

use DateTimeImmutable;
use Messaging\Application\Port\Outbound\{MessagingMemberDirectoryPort, MessagingMessageRepositoryPort, MessagingParticipantRepositoryPort, MessagingSavedMessageRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Application\UseCase\Command\Message\UnsaveMessage\{UnsaveMessageCommand, UnsaveMessageHandler};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\ValueObject\MessageId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test UnsaveMessageHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UnsaveMessageHandler::class)]
final class UnsaveMessageHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string CONVERSATION_ID = 'conversation-1';

  private const string MESSAGE_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string AUTHOR_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string SAVING_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440003';

  #[Test]
  public function testInvokeUnsavesTheMessage(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::SAVING_MEMBER_ID);

    /** @var MessagingSavedMessageRepositoryPort&MockObject $savedMessages */
    $savedMessages = $this->createMock(MessagingSavedMessageRepositoryPort::class);
    $savedMessages->expects(self::once())
      ->method('unsave')
      ->with(self::MESSAGE_ID, self::SAVING_MEMBER_ID);

    $handler = new UnsaveMessageHandler(
      $messages,
      $savedMessages,
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $result = $handler->__invoke(new UnsaveMessageCommand('user-1', self::MESSAGE_ID));

    self::assertSame(self::MESSAGE_ID, $result->message->id);
  }

  #[Test]
  public function testInvokeAllowsUnsavingATombstonedMessage(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message(deleted: true));

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::SAVING_MEMBER_ID);

    /** @var MessagingSavedMessageRepositoryPort&MockObject $savedMessages */
    $savedMessages = $this->createMock(MessagingSavedMessageRepositoryPort::class);
    $savedMessages->expects(self::once())->method('unsave');

    $handler = new UnsaveMessageHandler(
      $messages,
      $savedMessages,
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $handler->__invoke(new UnsaveMessageCommand('user-1', self::MESSAGE_ID));

    self::addToAssertionCount(1);
  }

  #[Test]
  public function testInvokeIsIdempotentWhenTheMessageWasNeverSaved(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::SAVING_MEMBER_ID);

    $savedMessages = $this->createStub(MessagingSavedMessageRepositoryPort::class);

    $handler = new UnsaveMessageHandler(
      $messages,
      $savedMessages,
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $handler->__invoke(new UnsaveMessageCommand('user-1', self::MESSAGE_ID));

    self::addToAssertionCount(1);
  }

  #[Test]
  public function testInvokeThrowsWhenMessageIsNotFound(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn(null);

    $handler = new UnsaveMessageHandler(
      $messages,
      $this->createStub(MessagingSavedMessageRepositoryPort::class),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new UnsaveMessageCommand('user-1', self::MESSAGE_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenActorIsNotAnActiveMember(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(null);

    $handler = new UnsaveMessageHandler(
      $messages,
      $this->createStub(MessagingSavedMessageRepositoryPort::class),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new UnsaveMessageCommand('user-1', self::MESSAGE_ID));
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
}
