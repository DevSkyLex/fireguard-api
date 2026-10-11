<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Infrastructure\Adapter\Realtime;

use DateTimeImmutable;
use Messaging\Infrastructure\Adapter\Realtime\MercureMessagingSubscriptionTokenAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;

/**
 * Class MercureMessagingSubscriptionTokenAdapterTest
 *
 * Pins exact topic scope, absent publication capability and subscriber expiration.
 *
 * @category Adapter Tests
 */
#[CoversClass(MercureMessagingSubscriptionTokenAdapter::class)]
final class MercureMessagingSubscriptionTokenAdapterTest extends TestCase
{
  #[Test]
  public function grantsOneConversationAndNoPublicationTopics(): void
  {
    $now = new DateTimeImmutable();
    $factory = $this->createMock(TokenFactoryInterface::class);
    $factory->expects(self::once())->method('create')->with(
      ['/organizations/organization/conversations/conversation'],
      [],
      self::callback(static fn (array $claims): bool => $claims['exp'] instanceof DateTimeImmutable && $claims['exp']->getTimestamp() >= $now->getTimestamp() + 60 && $claims['exp']->getTimestamp() <= $now->getTimestamp() + 65),
    )->willReturn('opaque-token');
    $result = new MercureMessagingSubscriptionTokenAdapter($factory, 60)->create('organization', 'conversation');

    self::assertSame('opaque-token', $result->token);
    self::assertSame('/organizations/organization/conversations/conversation', $result->topic);
  }
}
