<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Conversation\GetOrCreateDirectConversation;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort, MessagingReadMarkerRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Application\UseCase\Command\Conversation\GetOrCreateDirectConversation\{GetOrCreateDirectConversationCommand, GetOrCreateDirectConversationHandler};
use Messaging\Domain\Exception\MessagingValidationException;
use Messaging\Domain\Service\DirectConversationKey;
use Messaging\Domain\ValueObject\{ConversationVisibility, MessagingSubjectType};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test GetOrCreateDirectConversationHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetOrCreateDirectConversationHandler::class)]
final class GetOrCreateDirectConversationHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string USER_ID = 'user-1';

  private const string CALLER_MEMBER_ID = 'member-1';

  private const string OTHER_MEMBER_ID = 'member-2';

  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655440099';

  #[Test]
  public function testInvokeCreatesADirectConversationAndSeedsBothParticipantsOnFirstOpen(): void
  {
    $expectedKey = DirectConversationKey::for(self::CALLER_MEMBER_ID, self::OTHER_MEMBER_ID);
    $view = $this->view();

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::CALLER_MEMBER_ID);
    $members->method('memberIsActive')->willReturn(true);

    /** @var MessagingConversationRepositoryPort&MockObject $conversations */
    $conversations = $this->createMock(MessagingConversationRepositoryPort::class);
    $conversations->expects(self::once())
      ->method('getOrCreate')
      ->with(self::ORG_ID, MessagingSubjectType::DIRECT, $expectedKey, ConversationVisibility::PARTICIPANTS)
      ->willReturn($view);

    $participants = $this->createMock(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn(false);
    $participants->expects(self::exactly(2))->method('addParticipant');

    $readMarkers = $this->createMock(MessagingReadMarkerRepositoryPort::class);
    $readMarkers->expects(self::exactly(2))->method('upsert');

    $handler = $this->handler($conversations, $participants, $readMarkers, $members);

    $result = $handler->__invoke(new GetOrCreateDirectConversationCommand(self::USER_ID, self::ORG_ID, self::OTHER_MEMBER_ID));

    self::assertSame($view->id, $result->conversation->id);
  }

  #[Test]
  public function testInvokeIsIdempotentAndSkipsReSeedingWhenTheCallerIsAlreadyAParticipant(): void
  {
    $view = $this->view();

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::CALLER_MEMBER_ID);
    $members->method('memberIsActive')->willReturn(true);

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('getOrCreate')->willReturn($view);

    $participants = $this->createMock(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn(true);
    $participants->expects(self::never())->method('addParticipant');

    $readMarkers = $this->createMock(MessagingReadMarkerRepositoryPort::class);
    $readMarkers->expects(self::never())->method('upsert');

    $handler = $this->handler($conversations, $participants, $readMarkers, $members);

    $handler->__invoke(new GetOrCreateDirectConversationCommand(self::USER_ID, self::ORG_ID, self::OTHER_MEMBER_ID));
    $handler->__invoke(new GetOrCreateDirectConversationCommand(self::USER_ID, self::ORG_ID, self::OTHER_MEMBER_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenStartingADirectConversationWithYourself(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::CALLER_MEMBER_ID);

    $handler = $this->handler(
      $this->createStub(MessagingConversationRepositoryPort::class),
      $this->createStub(MessagingParticipantRepositoryPort::class),
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      $members,
    );

    $this->expectException(MessagingValidationException::class);

    $handler->__invoke(new GetOrCreateDirectConversationCommand(self::USER_ID, self::ORG_ID, self::CALLER_MEMBER_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenTheOtherMemberIsNotActive(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::CALLER_MEMBER_ID);
    $members->method('memberIsActive')->willReturn(false);

    $handler = $this->handler(
      $this->createStub(MessagingConversationRepositoryPort::class),
      $this->createStub(MessagingParticipantRepositoryPort::class),
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      $members,
    );

    $this->expectException(MessagingValidationException::class);

    $handler->__invoke(new GetOrCreateDirectConversationCommand(self::USER_ID, self::ORG_ID, self::OTHER_MEMBER_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenTheMessagingReadPermissionIsMissing(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')->willThrowException(new OrganizationAccessDeniedException('Missing permission.'));

    $accessPolicy = new MessagingAccessPolicy($authorization, $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class));

    $handler = new GetOrCreateDirectConversationHandler(
      $this->createStub(MessagingConversationRepositoryPort::class),
      $this->createStub(MessagingParticipantRepositoryPort::class),
      $this->createStub(MessagingReadMarkerRepositoryPort::class),
      $this->createStub(MessagingMemberDirectoryPort::class),
      $accessPolicy,
    );

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler->__invoke(new GetOrCreateDirectConversationCommand(self::USER_ID, self::ORG_ID, self::OTHER_MEMBER_ID));
  }

  private function handler(
    MessagingConversationRepositoryPort $conversations,
    MessagingParticipantRepositoryPort $participants,
    MessagingReadMarkerRepositoryPort $readMarkers,
    MessagingMemberDirectoryPort $members,
  ): GetOrCreateDirectConversationHandler {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions');
    $accessPolicy = new MessagingAccessPolicy($authorization, $members, $participants);

    return new GetOrCreateDirectConversationHandler($conversations, $participants, $readMarkers, $members, $accessPolicy);
  }

  private function view(): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(
      self::CONVERSATION_ID,
      self::ORG_ID,
      'direct',
      DirectConversationKey::for(self::CALLER_MEMBER_ID, self::OTHER_MEMBER_ID),
      'participants',
      null,
      0,
      false,
      $now,
      $now,
    );
  }
}
