<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Plan;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\UseCase\Query\Plan\GetPlan\{GetPlanQuery, GetPlanResult};
use Organization\Domain\Exception\PlanNotFoundException;
use Organization\Presentation\Api\Provider\Plan\GetPlanProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException};

/**
 * Test GetPlanProvider.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetPlanProvider::class)]
final class GetPlanProviderTest extends TestCase
{
  private const string PLAN_ID = '22222222-2222-4222-8222-222222222222';

  #[Test]
  public function testProvideThrowsWhenTheUserIsNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new GetPlanProvider($this->createStub(QueryBusPort::class), $security);

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['id' => self::PLAN_ID]);
  }

  #[Test]
  public function testProvideReturnsNullWhenThePlanIdIsMissing(): void
  {
    $provider = new GetPlanProvider($this->createStub(QueryBusPort::class), $this->authenticatedSecurity());

    self::assertNull($provider->provide(new Get(), []));
  }

  #[Test]
  public function testProvideTranslatesAMissingPlanIntoANotFoundResponse(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(PlanNotFoundException::withId(self::PLAN_ID));

    $provider = new GetPlanProvider($queryBus, $this->authenticatedSecurity());

    $this->expectException(PlanNotFoundException::class);

    $provider->provide(new Get(), ['id' => self::PLAN_ID]);
  }

  #[Test]
  public function testProvideMapsTheResultOntoThePlanOutput(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturnCallback(
      function (object $query) use (&$asked): GetPlanResult {
        $asked = $query;

        return new GetPlanResult(
          id: self::PLAN_ID,
          key: 'pro',
          name: 'Pro',
          limits: ['members' => 50],
          quotas: [],
          isActive: true,
          isDefault: false,
          sortOrder: 2,
          createdAt: new DateTimeImmutable('2026-01-01T09:00:00+00:00'),
          updatedAt: new DateTimeImmutable('2026-01-02T09:00:00+00:00'),
        );
      },
    );

    $provider = new GetPlanProvider($queryBus, $this->authenticatedSecurity());

    $output = $provider->provide(new Get(), ['id' => self::PLAN_ID]);

    self::assertInstanceOf(GetPlanQuery::class, $asked);
    self::assertNotNull($output);
    self::assertSame(self::PLAN_ID, $output->id);
    self::assertSame('pro', $output->key);
    self::assertSame('Pro', $output->name);
  }

  /**
   * Builds a security stub returning an authenticated user.
   */
  private function authenticatedSecurity(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: '550e8400-e29b-41d4-a716-446655441800',
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }
}
