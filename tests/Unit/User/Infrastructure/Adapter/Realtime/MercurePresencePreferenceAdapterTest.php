<?php

declare(strict_types=1);

namespace Tests\Unit\User\Infrastructure\Adapter\Realtime;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\{HubInterface, Update};
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use User\Infrastructure\Adapter\Realtime\MercurePresencePreferenceAdapter;

use function json_decode;
use function time;

/**
 * Test MercurePresencePreferenceAdapterTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MercurePresencePreferenceAdapterTest extends TestCase
{
  #[Test]
  /**
   * @since 1.0.0
   */
  public function subscriptionGrantsOnlyOneExactTopicAndNoPublishRights(): void
  {
    $factory = $this->createMock(TokenFactoryInterface::class);
    $factory->expects(self::once())->method('create')->with(['/users/id/presence-preference'], [], self::callback(static fn (array $claims): bool => isset($claims['exp']) && $claims['exp'] instanceof DateTimeImmutable))->willReturn('token');
    $adapter = new MercurePresencePreferenceAdapter($this->createStub(HubInterface::class), $factory, 900);
    $result = $adapter->subscribe('id');
    self::assertSame('/users/id/presence-preference', $result->topic);
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
      self::assertSame(['/users/id/presence-preference'], $update->getTopics());
      self::assertNull($update->getType());
      self::assertSame(['type' => 'presence.preference.changed', 'doNotDisturb' => true, 'revision' => 2, 'invisible' => true], json_decode($update->getData(), true));

      return true;
    }))->willReturn('event-id');
    new MercurePresencePreferenceAdapter($hub, $this->createStub(TokenFactoryInterface::class))->publish('id', true, 2, true);
  }
}
