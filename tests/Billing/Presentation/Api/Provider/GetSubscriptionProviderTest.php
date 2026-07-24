<?php

declare(strict_types=1);

namespace Tests\Billing\Presentation\Api\Provider;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Billing\Application\Port\Outbound\OrganizationAccessPort;
use Billing\Application\UseCase\Query\GetOrganizationSubscription\{
  GetOrganizationSubscriptionQuery,
  GetOrganizationSubscriptionResult
};
use Billing\Presentation\Api\Provider\GetSubscriptionProvider;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Test GetSubscriptionProviderTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetSubscriptionProvider::class)]
final class GetSubscriptionProviderTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441600';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655441610';

  #[Test]
  public function itReturnsNullWhenTheOrganizationIdIsMissing(): void
  {
    $security = $this->securityWithUser();

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetSubscriptionProvider(
      queryBus: $queryBus,
      access: $this->createStub(OrganizationAccessPort::class),
      security: $security,
    );

    self::assertNull($provider->provide(new Get(), []));
  }

  #[Test]
  public function itThrowsWhenThePermissionIsMissing(): void
  {
    $security = $this->securityWithUser();

    /** @var OrganizationAccessPort&MockObject $access */
    $access = $this->createMock(OrganizationAccessPort::class);
    $access->expects(self::once())
      ->method('hasPermission')
      ->with(self::USER_ID, self::ORGANIZATION_ID, 'organization.read')
      ->willReturn(false);

    $provider = new GetSubscriptionProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      access: $access,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function itMapsThePlanNameAndPricingSoTheCurrentPlanCardNeedsOneCall(): void
  {
    $security = $this->securityWithUser();

    $access = $this->createStub(OrganizationAccessPort::class);
    $access->method('hasPermission')->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetOrganizationSubscriptionQuery::class))
      ->willReturn(new GetOrganizationSubscriptionResult(
        organizationId: self::ORGANIZATION_ID,
        hasSubscription: true,
        active: true,
        status: 'active',
        planKey: 'pro',
        planName: 'Pro',
        interval: 'month',
        currentPeriodEnd: new DateTimeImmutable('2026-08-01T00:00:00+00:00'),
        cancelAtPeriodEnd: false,
        currency: 'eur',
        monthlyAmount: 1000,
        yearlyAmount: 10000,
      ));

    $provider = new GetSubscriptionProvider(
      queryBus: $queryBus,
      access: $access,
      security: $security,
    );

    $output = $provider->provide(new Get(), ['organizationId' => self::ORGANIZATION_ID]);

    self::assertNotNull($output);
    self::assertSame(self::ORGANIZATION_ID, $output->organizationId);
    self::assertTrue($output->hasSubscription);
    self::assertTrue($output->active);
    self::assertSame('active', $output->status);
    self::assertSame('pro', $output->planKey);
    self::assertSame('Pro', $output->planName);
    self::assertSame('month', $output->interval);
    self::assertSame('2026-08-01T00:00:00+00:00', $output->currentPeriodEnd);
    self::assertFalse($output->cancelAtPeriodEnd);
    self::assertSame('eur', $output->currency);
    self::assertSame(1000, $output->monthlyAmount);
    self::assertSame(10000, $output->yearlyAmount);
  }

  #[Test]
  public function itLeavesThePlanNameAndPricingNullWhenTheHandlerReturnsNone(): void
  {
    $security = $this->securityWithUser();

    $access = $this->createStub(OrganizationAccessPort::class);
    $access->method('hasPermission')->willReturn(true);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new GetOrganizationSubscriptionResult(
      organizationId: self::ORGANIZATION_ID,
      hasSubscription: false,
      active: false,
    ));

    $provider = new GetSubscriptionProvider(
      queryBus: $queryBus,
      access: $access,
      security: $security,
    );

    $output = $provider->provide(new Get(), ['organizationId' => self::ORGANIZATION_ID]);

    self::assertNotNull($output);
    self::assertFalse($output->hasSubscription);
    self::assertNull($output->planKey);
    self::assertNull($output->planName);
    self::assertNull($output->currency);
    self::assertNull($output->monthlyAmount);
    self::assertNull($output->yearlyAmount);
  }

  private function securityWithUser(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }
}
