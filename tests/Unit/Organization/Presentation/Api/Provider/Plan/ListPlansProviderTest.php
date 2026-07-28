<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Plan;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\UseCase\Query\Plan\GetPlan\GetPlanResult;
use Organization\Application\UseCase\Query\Plan\ListPlans\{ListPlansQuery, ListPlansResult};
use Organization\Presentation\Api\Provider\Plan\ListPlansProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Test ListPlansProvider.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListPlansProvider::class)]
final class ListPlansProviderTest extends TestCase
{
  #[Test]
  public function testProvideThrowsWhenTheUserIsNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new ListPlansProvider($this->createStub(QueryBusPort::class), $security);

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideRestrictsRegularUsersToActivePlans(): void
  {
    $provider = new ListPlansProvider($this->queryBus($asked), $this->securityFor(false));

    $outputs = $provider->provide(new GetCollection());

    self::assertInstanceOf(ListPlansQuery::class, $asked);
    self::assertTrue($asked->activeOnly);
    self::assertCount(1, $outputs);
    self::assertSame('pro', $outputs[0]->key);
  }

  #[Test]
  public function testProvideLetsAdministratorsSeeEveryPlan(): void
  {
    $provider = new ListPlansProvider($this->queryBus($asked), $this->securityFor(true));

    $provider->provide(new GetCollection());

    self::assertInstanceOf(ListPlansQuery::class, $asked);
    self::assertFalse($asked->activeOnly);
  }

  /**
   * Builds a query bus stub recording the dispatched query.
   */
  private function queryBus(?object &$asked): QueryBusPort
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturnCallback(
      static function (object $query) use (&$asked): ListPlansResult {
        $asked = $query;

        return new ListPlansResult([
          new GetPlanResult(
            id: '22222222-2222-4222-8222-222222222222',
            key: 'pro',
            name: 'Pro',
            limits: [],
            quotas: [],
            isActive: true,
            isDefault: false,
            sortOrder: 1,
            createdAt: new DateTimeImmutable('2026-01-01T09:00:00+00:00'),
            updatedAt: new DateTimeImmutable('2026-01-01T09:00:00+00:00'),
          ),
        ]);
      },
    );

    return $queryBus;
  }

  /**
   * Builds a security stub for an authenticated user with or without the admin role.
   */
  private function securityFor(bool $isAdmin): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: '550e8400-e29b-41d4-a716-446655441800',
      email: 'user@example.com',
      password: 'hashed-password',
      roles: $isAdmin ? ['ROLE_USER', 'ROLE_ADMIN'] : ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));
    $security->method('isGranted')->willReturn($isAdmin);

    return $security;
  }
}
