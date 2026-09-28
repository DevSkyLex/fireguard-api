<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Infrastructure\Adapter\Realtime;

use DateTimeImmutable;
use Messaging\Infrastructure\Adapter\Realtime\MercurePresenceAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\{HubInterface, Update};
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;

use function json_decode;
use function time;

/**
 * Test MercurePresenceAdapterTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MercurePresenceAdapterTest extends TestCase
{
  #[Test]
  /**
   * @since 1.0.0
   */
  public function subscriptionGrantsOnlyOneExactTopicAndNoPublishRights(): void
  {
    $factory = $this->createMock(TokenFactoryInterface::class);
    $factory->expects(self::once())->method('create')->with(['/organizations/id/presence'], [], self::callback(static fn (array $claims): bool => isset($claims['exp']) && $claims['exp'] instanceof DateTimeImmutable))->willReturn('token');
    $adapter = new MercurePresenceAdapter($this->createStub(HubInterface::class), $factory, 900);
    $result = $adapter->subscribe('id');
    self::assertSame('/organizations/id/presence', $result->topic);
    self::assertSame('token', $result->token);
    self::assertGreaterThan(time(), new DateTimeImmutable($result->expiresAt)->getTimestamp());
  }

  #[Test]
  /**
   * @since 1.0.0
   */
  public function publishesPrivateOrdinarySseFrame(): void
  {
    $hub = $this->createMock(HubInterface::class);
    $hub->expects(self::once())->method('publish')->with(self::callback(static function (Update $update): bool {
      self::assertTrue($update->isPrivate());
      self::assertSame(['/organizations/id/presence'], $update->getTopics());
      self::assertNull($update->getType());
      self::assertSame(['type' => 'presence.changed', 'organizationId' => 'id', 'memberId' => 'member'], json_decode($update->getData(), true));

      return true;
    }))->willReturn('event-id');
    new MercurePresenceAdapter($hub, $this->createStub(TokenFactoryInterface::class))->publish('id', 'member');
  }
}
