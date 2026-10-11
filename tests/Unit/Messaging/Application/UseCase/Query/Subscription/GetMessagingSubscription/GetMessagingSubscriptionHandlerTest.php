<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Query\Subscription\GetMessagingSubscription;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Contract\Subscription\MessagingSubscription;
use Messaging\Application\Port\Outbound\MessagingSubscriptionTokenPort;
use Messaging\Application\UseCase\Query\Conversation\GetConversation\{GetConversationQuery, GetConversationResult};
use Messaging\Application\UseCase\Query\Subscription\GetMessagingSubscription\{GetMessagingSubscriptionHandler, GetMessagingSubscriptionQuery};
use Messaging\Domain\Exception\MessagingAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;

/**
 * Class GetMessagingSubscriptionHandlerTest
 *
 * Verifies authorization precedes subscription capability generation.
 *
 * @category Handler Tests
 */
#[CoversClass(GetMessagingSubscriptionHandler::class)]
final class GetMessagingSubscriptionHandlerTest extends TestCase
{
  #[Test]
  public function createsTheTokenOnlyAfterTheCanonicalConversationReadGate(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->with(self::callback(static fn (GetConversationQuery $query): bool => 'actor' === $query->userId && 'conversation' === $query->conversationId))
      ->willReturn(new GetConversationResult(new ConversationView('conversation', 'organization', 'facility', null, 'subject', null, 0, false, new DateTimeImmutable(), new DateTimeImmutable()), null, 0));
    $subscription = new MessagingSubscription('opaque-token', '/organizations/organization/conversations/conversation');
    $tokens = $this->createMock(MessagingSubscriptionTokenPort::class);
    $tokens->expects(self::once())->method('create')->with('organization', 'conversation')->willReturn($subscription);

    self::assertSame($subscription, new GetMessagingSubscriptionHandler($queries, $tokens)(new GetMessagingSubscriptionQuery('actor', 'conversation'))->subscription);
  }

  #[Test]
  public function neverCreatesATokenForADeniedConversation(): void
  {
    $queries = $this->createStub(QueryBusPort::class);
    $queries->method('ask')->willThrowException(new MessagingAccessDeniedException('Not a participant'));
    $tokens = $this->createMock(MessagingSubscriptionTokenPort::class);
    $tokens->expects(self::never())->method('create');
    $this->expectException(MessagingAccessDeniedException::class);
    new GetMessagingSubscriptionHandler($queries, $tokens)(new GetMessagingSubscriptionQuery('actor', 'conversation'));
  }
}
