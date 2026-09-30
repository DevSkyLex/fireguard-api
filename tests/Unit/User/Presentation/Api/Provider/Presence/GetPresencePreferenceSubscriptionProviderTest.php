<?php

declare(strict_types=1);

namespace Tests\Unit\User\Presentation\Api\Provider\Presence;

use ApiPlatform\Metadata\Get;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use User\Application\UseCase\Query\Presence\GetPresencePreferenceSubscription\{GetPresencePreferenceSubscriptionQuery, GetPresencePreferenceSubscriptionResult};
use User\Presentation\Api\Provider\Presence\GetPresencePreferenceSubscriptionProvider;

/**
 * Test GetPresencePreferenceSubscriptionProviderTest.
 *
 * @category Test
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GetPresencePreferenceSubscriptionProviderTest extends TestCase
{
  #[Test]
  /**
   * @since 1.0.0
   */
  public function translatesOnlyCurrentActor(): void
  {
    $actor = $this->createStub(CurrentActorPort::class);
    $actor->method('userId')->willReturn('user');
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::once())->method('ask')->with(self::callback(static fn (GetPresencePreferenceSubscriptionQuery $query): bool => 'user' === $query->userId))->willReturn(new GetPresencePreferenceSubscriptionResult('/users/user/presence-preference', 'token', '2026-09-26T00:15:00+00:00'));
    $result = new GetPresencePreferenceSubscriptionProvider($bus, $actor)->provide(new Get());
    self::assertSame('/users/user/presence-preference', $result->topic);
    self::assertSame('token', $result->token);
  }

  #[Test]
  /**
   * @since 1.0.0
   */
  public function deniesAnonymousBeforeBus(): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::never())->method('ask');
    $this->expectException(AccessDeniedHttpException::class);
    new GetPresencePreferenceSubscriptionProvider($bus, $this->createStub(CurrentActorPort::class))->provide(new Get());
  }
}
