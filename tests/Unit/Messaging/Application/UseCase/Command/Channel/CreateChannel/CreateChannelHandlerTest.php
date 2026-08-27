<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Channel\CreateChannel;

use DateTimeImmutable;
use Messaging\Application\Contract\Channel\ChannelView;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort, MessagingReadMarkerRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Application\UseCase\Command\Channel\CreateChannel\{CreateChannelCommand, CreateChannelHandler};
use Messaging\Domain\Exception\{MessagingNotFoundException, MessagingValidationException};
use Messaging\Domain\ValueObject\ConversationId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Test CreateChannelHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateChannelHandler::class)]
final class CreateChannelHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string USER_ID = 'user-1';

  private const string MEMBER_ID = 'member-1';

  private const string CHANNEL_ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testInvokeCreatesTheChannelAddsTheCreatorAndSeedsTheirReadMarker(): void
  {
    $conversations = $this->createMock(MessagingConversationRepositoryPort::class);
    $conversations->expects(self::once())->method('createChannel')->willReturn($this->conversationView());
    $conversations->method('findChannelById')->willReturn($this->channelView());

    $participants = $this->createMock(MessagingParticipantRepositoryPort::class);
    $participants->expects(self::once())
      ->method('addParticipant')
      ->with(self::CHANNEL_ID, self::ORG_ID, self::MEMBER_ID, null, 'manual', self::isInstanceOf(DateTimeImmutable::class));

    $readMarkers = $this->createMock(MessagingReadMarkerRepositoryPort::class);
    $readMarkers->expects(self::once())
      ->method('upsert')
      ->with(self::CHANNEL_ID, self::ORG_ID, self::MEMBER_ID, self::isInstanceOf(DateTimeImmutable::class), null);

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch');

    $handler = $this->handler($conversations, $participants, $readMarkers, $eventDispatcher);

    $result = $handler->__invoke(new CreateChannelCommand(self::USER_ID, self::ORG_ID, 'General'));

    self::assertSame(self::CHANNEL_ID, $result->channel->id);
  }

  #[Test]
  public function testInvokeThrowsWhenManagePermissionIsMissing(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')->willThrowException(new OrganizationAccessDeniedException('Missing permission.'));

    $handler = $this->handler(
      $this->createStub(MessagingConversationRepositoryPort::class),
      $this->createStub(MessagingParticipantRepositoryPort::class),
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      $this->createStub(EventDispatcherPort::class),
      $authorization,
    );

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler->__invoke(new CreateChannelCommand(self::USER_ID, self::ORG_ID, 'General'));
  }

  #[Test]
  public function testInvokeThrowsWhenTheNameIsInvalid(): void
  {
    $handler = $this->handler(
      $this->createStub(MessagingConversationRepositoryPort::class),
      $this->createStub(MessagingParticipantRepositoryPort::class),
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(MessagingValidationException::class);

    $handler->__invoke(new CreateChannelCommand(self::USER_ID, self::ORG_ID, 'A'));
  }

  #[Test]
  public function testInvokeThrowsWhenTheActorIsNotAnActiveMember(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(null);

    $handler = $this->handler(
      $this->createStub(MessagingConversationRepositoryPort::class),
      $this->createStub(MessagingParticipantRepositoryPort::class),
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      $this->createStub(EventDispatcherPort::class),
      null,
      $members,
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new CreateChannelCommand(self::USER_ID, self::ORG_ID, 'General'));
  }

  #[Test]
  public function testInvokeThrowsWhenTheCreatedChannelCannotBeReadBack(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('createChannel')->willReturn($this->conversationView());
    $conversations->method('findChannelById')->willReturn(null);

    $handler = $this->handler(
      $conversations,
      $this->createStub(MessagingParticipantRepositoryPort::class),
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new CreateChannelCommand(self::USER_ID, self::ORG_ID, 'General'));
  }

  private function handler(
    MessagingConversationRepositoryPort $conversations,
    MessagingParticipantRepositoryPort $participants,
    MessagingReadMarkerRepositoryPort $readMarkers,
    EventDispatcherPort $eventDispatcher,
    ?OrganizationAuthorizationPort $authorization = null,
    ?MessagingMemberDirectoryPort $members = null,
  ): CreateChannelHandler {
    $authorization ??= $this->createStub(OrganizationAuthorizationPort::class);

    if (null === $members) {
      $members = $this->createStub(MessagingMemberDirectoryPort::class);
      $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);
    }

    $accessPolicy = new MessagingAccessPolicy($authorization, $members, $participants);

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(ConversationId::fromString(self::CHANNEL_ID));

    return new CreateChannelHandler($conversations, $participants, $readMarkers, $accessPolicy, $uuidFactory, $eventDispatcher);
  }

  private function channelView(): ChannelView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ChannelView(self::CHANNEL_ID, self::ORG_ID, 'General', null, self::MEMBER_ID, 1, false, null, 0, $now, $now);
  }

  private function conversationView(): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(self::CHANNEL_ID, self::ORG_ID, 'channel', null, 'participants', null, 0, false, $now, $now, 'General', null, self::MEMBER_ID);
  }
}
