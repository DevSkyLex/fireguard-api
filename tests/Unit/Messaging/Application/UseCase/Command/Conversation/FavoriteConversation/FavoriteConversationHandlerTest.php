<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Conversation\FavoriteConversation;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Contract\Subject\MessagingSubjectResolution;
use Messaging\Application\Port\Outbound\{MessagingConversationFavoriteRepositoryPort, MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort, MessagingSubjectResolverPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Application\UseCase\Command\Conversation\FavoriteConversation\{FavoriteConversationCommand, FavoriteConversationHandler};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException};
use Messaging\Domain\ValueObject\MessagingSubjectType;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test FavoriteConversationHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FavoriteConversationHandler::class)]
final class FavoriteConversationHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655440000';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655440003';

  #[Test]
  public function testInvokeFavoritesTheConversation(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->conversationView());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);

    /** @var MessagingConversationFavoriteRepositoryPort&MockObject $favorites */
    $favorites = $this->createMock(MessagingConversationFavoriteRepositoryPort::class);
    $favorites->expects(self::once())
      ->method('favorite')
      ->with(self::CONVERSATION_ID, self::ORG_ID, self::MEMBER_ID);

    $handler = new FavoriteConversationHandler(
      $conversations,
      $favorites,
      new MessagingSubjectResolverRegistry([$this->facilityResolver()]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $result = $handler->__invoke(new FavoriteConversationCommand('user-1', self::CONVERSATION_ID));

    self::assertSame(self::CONVERSATION_ID, $result->conversation->id);
  }

  #[Test]
  public function testInvokeThrowsWhenConversationIsNotFound(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn(null);

    $handler = new FavoriteConversationHandler(
      $conversations,
      $this->createStub(MessagingConversationFavoriteRepositoryPort::class),
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new FavoriteConversationCommand('user-1', self::CONVERSATION_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenReadPermissionIsMissing(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->conversationView());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')->willThrowException(new MessagingAccessDeniedException('Missing permission.'));

    $favorites = $this->createMock(MessagingConversationFavoriteRepositoryPort::class);
    $favorites->expects(self::never())->method('favorite');

    $handler = new FavoriteConversationHandler(
      $conversations,
      $favorites,
      new MessagingSubjectResolverRegistry([$this->facilityResolver()]),
      new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $this->expectException(MessagingAccessDeniedException::class);

    $handler->__invoke(new FavoriteConversationCommand('user-1', self::CONVERSATION_ID));
  }

  private function facilityResolver(): MessagingSubjectResolverPort
  {
    $resolver = $this->createStub(MessagingSubjectResolverPort::class);
    $resolver->method('supports')->willReturnCallback(static fn (MessagingSubjectType $type): bool => MessagingSubjectType::FACILITY === $type);
    $resolver->method('resolve')->willReturn(new MessagingSubjectResolution(true, 'Site nord', 'organization.facilities.read'));

    return $resolver;
  }

  private function conversationView(): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(self::CONVERSATION_ID, self::ORG_ID, 'facility', 'facility-1', 'subject', null, 1, false, $now, $now);
  }
}
