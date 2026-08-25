<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Message\RemoveReaction;

use DateTimeImmutable;
use Messaging\Application\Port\Outbound\{MessagingMemberDirectoryPort, MessagingMessageRepositoryPort, MessagingParticipantRepositoryPort, MessagingReactionRepositoryPort, MessagingRealtimePublisherPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Application\UseCase\Command\Message\RemoveReaction\{RemoveReactionCommand, RemoveReactionHandler};
use Messaging\Domain\Exception\{MessagingNotFoundException, MessagingValidationException};
use Messaging\Domain\Model\Message\Message;
use Messaging\Domain\ValueObject\MessageId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\LoggerPort;

/**
 * Test RemoveReactionHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RemoveReactionHandler::class)]
final class RemoveReactionHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string CONVERSATION_ID = 'conversation-1';

  private const string MESSAGE_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string AUTHOR_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string REACTING_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440003';

  #[Test]
  public function testInvokeRemovesTheReactionAndPublishesRealtime(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::REACTING_MEMBER_ID);

    /** @var MessagingReactionRepositoryPort&MockObject $reactions */
    $reactions = $this->createMock(MessagingReactionRepositoryPort::class);
    $reactions->expects(self::once())
      ->method('remove')
      ->with(self::MESSAGE_ID, self::REACTING_MEMBER_ID, "\u{1F44D}");

    $realtime = $this->createMock(MessagingRealtimePublisherPort::class);
    $realtime->expects(self::once())->method('publishMessage')->with(self::ORG_ID, self::CONVERSATION_ID);

    $handler = new RemoveReactionHandler(
      $messages,
      $reactions,
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
      $realtime,
      $this->createStub(LoggerPort::class),
    );

    $result = $handler->__invoke(new RemoveReactionCommand('user-1', self::MESSAGE_ID, "\u{1F44D}"));

    self::assertSame(self::MESSAGE_ID, $result->message->id);
  }

  #[Test]
  public function testInvokeAllowsRemovingAReactionFromATombstonedMessage(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message(deleted: true));

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::REACTING_MEMBER_ID);

    /** @var MessagingReactionRepositoryPort&MockObject $reactions */
    $reactions = $this->createMock(MessagingReactionRepositoryPort::class);
    $reactions->expects(self::once())->method('remove');

    $handler = new RemoveReactionHandler(
      $messages,
      $reactions,
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
      $this->createStub(MessagingRealtimePublisherPort::class),
      $this->createStub(LoggerPort::class),
    );

    $handler->__invoke(new RemoveReactionCommand('user-1', self::MESSAGE_ID, "\u{1F44D}"));

    self::addToAssertionCount(1);
  }

  #[Test]
  public function testInvokeThrowsWhenMessageIsNotFound(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn(null);

    $handler = new RemoveReactionHandler(
      $messages,
      $this->createStub(MessagingReactionRepositoryPort::class),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
      $this->createStub(MessagingRealtimePublisherPort::class),
      $this->createStub(LoggerPort::class),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new RemoveReactionCommand('user-1', self::MESSAGE_ID, "\u{1F44D}"));
  }

  #[Test]
  public function testInvokeThrowsWhenTheEmojiIsNotAPlausibleGrapheme(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());

    $reactions = $this->createMock(MessagingReactionRepositoryPort::class);
    $reactions->expects(self::never())->method('remove');

    $handler = new RemoveReactionHandler(
      $messages,
      $reactions,
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
      $this->createStub(MessagingRealtimePublisherPort::class),
      $this->createStub(LoggerPort::class),
    );

    $this->expectException(MessagingValidationException::class);

    $handler->__invoke(new RemoveReactionCommand('user-1', self::MESSAGE_ID, 'not an emoji'));
  }

  #[Test]
  public function testInvokeThrowsWhenActorIsNotAnActiveMember(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(null);

    $handler = new RemoveReactionHandler(
      $messages,
      $this->createStub(MessagingReactionRepositoryPort::class),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
      $this->createStub(MessagingRealtimePublisherPort::class),
      $this->createStub(LoggerPort::class),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new RemoveReactionCommand('user-1', self::MESSAGE_ID, "\u{1F44D}"));
  }

  #[Test]
  public function testInvokeNeverFailsWhenRealtimePublishThrows(): void
  {
    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('findAggregateById')->willReturn($this->message());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::REACTING_MEMBER_ID);

    $realtime = $this->createStub(MessagingRealtimePublisherPort::class);
    $realtime->method('publishMessage')->willThrowException(new RuntimeException('Mercure unavailable'));

    $handler = new RemoveReactionHandler(
      $messages,
      $this->createStub(MessagingReactionRepositoryPort::class),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
      $realtime,
      $this->createStub(LoggerPort::class),
    );

    $result = $handler->__invoke(new RemoveReactionCommand('user-1', self::MESSAGE_ID, "\u{1F44D}"));

    self::assertSame(self::MESSAGE_ID, $result->message->id);
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
