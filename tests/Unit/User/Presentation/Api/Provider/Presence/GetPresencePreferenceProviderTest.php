<?php

declare(strict_types=1);

namespace Tests\Unit\User\Presentation\Api\Provider\Presence;

use ApiPlatform\Metadata\Get;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use User\Application\UseCase\Query\Presence\GetPresencePreference\{GetPresencePreferenceQuery, GetPresencePreferenceResult};
use User\Presentation\Api\Provider\Presence\GetPresencePreferenceProvider;

/**
 * Test GetPresencePreferenceProviderTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GetPresencePreferenceProviderTest extends TestCase
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
    $bus->expects(self::once())->method('ask')->with(self::callback(static fn (GetPresencePreferenceQuery $query): bool => 'user' === $query->userId))->willReturn(new GetPresencePreferenceResult(true, 4));
    $result = new GetPresencePreferenceProvider($bus, $actor)->provide(new Get());
    self::assertTrue($result->doNotDisturb);
    self::assertSame(4, $result->revision);
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
    new GetPresencePreferenceProvider($bus, $this->createStub(CurrentActorPort::class))->provide(new Get());
  }
}
