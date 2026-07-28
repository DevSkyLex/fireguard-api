<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Query\Message\GetConversationActivity;

use DateTimeImmutable;
use DateTimeZone;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Contract\Subject\MessagingSubjectResolution;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingMemberDirectoryPort, MessagingMessageRepositoryPort, MessagingParticipantRepositoryPort, MessagingSubjectResolverPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Application\UseCase\Query\Message\GetConversationActivity\{GetConversationActivityHandler, GetConversationActivityQuery};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException};
use Messaging\Domain\ValueObject\MessagingSubjectType;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetConversationActivityHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetConversationActivityHandler::class)]
final class GetConversationActivityHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string CONVERSATION_ID = 'conversation-1';

  #[Test]
  public function testInvokeReturnsBucketsZeroFilledFromTheRepositoryCounts(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->view());

    $today = new DateTimeImmutable('now', new DateTimeZone('UTC'))->format('Y-m-d');

    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages->expects(self::once())->method('countByConversationDay')->willReturn([$today => 3]);

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('member-1');

    $registry = new MessagingSubjectResolverRegistry([$this->facilityResolver()]);
    $accessPolicy = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class));

    $handler = new GetConversationActivityHandler($conversations, $messages, $registry, $accessPolicy);

    $result = $handler->__invoke(new GetConversationActivityQuery('user-1', self::CONVERSATION_ID, buckets: 5));

    self::assertCount(5, $result->buckets);
    self::assertSame($today, $result->buckets[4]['bucket']);
    self::assertSame(3, $result->buckets[4]['count']);
    // Every other bucket is zero-filled.
    self::assertSame(0, $result->buckets[0]['count']);
    self::assertSame(0, $result->buckets[1]['count']);
    self::assertSame(0, $result->buckets[2]['count']);
    self::assertSame(0, $result->buckets[3]['count']);
  }

  #[Test]
  public function testInvokeClampsBucketsToAtLeastOne(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->view());

    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('countByConversationDay')->willReturn([]);

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('member-1');

    $registry = new MessagingSubjectResolverRegistry([$this->facilityResolver()]);
    $accessPolicy = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class));

    $handler = new GetConversationActivityHandler($conversations, $messages, $registry, $accessPolicy);

    $result = $handler->__invoke(new GetConversationActivityQuery('user-1', self::CONVERSATION_ID, buckets: -5));

    self::assertCount(1, $result->buckets);
  }

  #[Test]
  public function testInvokeClampsBucketsToAtMost366(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->view());

    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('countByConversationDay')->willReturn([]);

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('member-1');

    $registry = new MessagingSubjectResolverRegistry([$this->facilityResolver()]);
    $accessPolicy = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class));

    $handler = new GetConversationActivityHandler($conversations, $messages, $registry, $accessPolicy);

    $result = $handler->__invoke(new GetConversationActivityQuery('user-1', self::CONVERSATION_ID, buckets: 10000));

    self::assertCount(366, $result->buckets);
  }

  #[Test]
  public function testInvokeDefaultsToTwentySixBuckets(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->view());

    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('countByConversationDay')->willReturn([]);

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('member-1');

    $registry = new MessagingSubjectResolverRegistry([$this->facilityResolver()]);
    $accessPolicy = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class));

    $handler = new GetConversationActivityHandler($conversations, $messages, $registry, $accessPolicy);

    $result = $handler->__invoke(new GetConversationActivityQuery('user-1', self::CONVERSATION_ID));

    self::assertCount(26, $result->buckets);
  }

  #[Test]
  public function testInvokeThrowsWhenConversationIsNotFound(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn(null);

    $handler = new GetConversationActivityHandler(
      $conversations,
      $this->createStub(MessagingMessageRepositoryPort::class),
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
    );

    $this->expectException(MessagingNotFoundException::class);

    $handler->__invoke(new GetConversationActivityQuery('user-1', self::CONVERSATION_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenSubjectReadPermissionIsMissing(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->view());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')->willThrowException(
      new MessagingAccessDeniedException('Missing permission.'),
    );

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('member-1');

    $registry = new MessagingSubjectResolverRegistry([$this->facilityResolver()]);
    $accessPolicy = new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class));

    $messages = $this->createMock(MessagingMessageRepositoryPort::class);
    $messages->expects(self::never())->method('countByConversationDay');

    $handler = new GetConversationActivityHandler($conversations, $messages, $registry, $accessPolicy);

    $this->expectException(MessagingAccessDeniedException::class);

    $handler->__invoke(new GetConversationActivityQuery('user-1', self::CONVERSATION_ID));
  }

  #[Test]
  public function testInvokeEnforcesChannelParticipationForAParticipantsConversation(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->channelView());

    $messages = $this->createStub(MessagingMessageRepositoryPort::class);
    $messages->method('countByConversationDay')->willReturn([]);

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('member-1');

    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn(true);

    $handler = new GetConversationActivityHandler(
      $conversations,
      $messages,
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $participants),
    );

    $result = $handler->__invoke(new GetConversationActivityQuery('user-1', self::CONVERSATION_ID, buckets: 3));

    self::assertCount(3, $result->buckets);
  }

  #[Test]
  public function testInvokeRejectsANonParticipantOfAChannel(): void
  {
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);
    $conversations->method('findById')->willReturn($this->channelView());

    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('member-1');

    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn(false);

    $handler = new GetConversationActivityHandler(
      $conversations,
      $this->createStub(MessagingMessageRepositoryPort::class),
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $participants),
    );

    $this->expectException(MessagingAccessDeniedException::class);

    $handler->__invoke(new GetConversationActivityQuery('user-1', self::CONVERSATION_ID));
  }

  private function channelView(): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(self::CONVERSATION_ID, self::ORG_ID, 'channel', null, 'participants', null, 0, false, $now, $now, 'general');
  }

  private function facilityResolver(): MessagingSubjectResolverPort
  {
    $resolver = $this->createStub(MessagingSubjectResolverPort::class);
    $resolver->method('supports')->willReturnCallback(static fn (MessagingSubjectType $type): bool => MessagingSubjectType::FACILITY === $type);
    $resolver->method('resolve')->willReturn(new MessagingSubjectResolution(true, 'Site nord', 'organization.facilities.read'));

    return $resolver;
  }

  private function view(): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(self::CONVERSATION_ID, self::ORG_ID, 'facility', 'facility-1', 'subject', null, 0, false, $now, $now);
  }
}
