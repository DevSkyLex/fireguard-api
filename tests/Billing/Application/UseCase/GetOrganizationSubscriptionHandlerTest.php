<?php

declare(strict_types=1);

namespace Tests\Billing\Application\UseCase;

use Billing\Application\Contract\Plan\PlanSummary;
use Billing\Application\Port\Outbound\{OrganizationPlanPort, SubscriptionRepositoryPort};
use Billing\Application\Service\BillingPriceCatalog;
use Billing\Application\UseCase\Query\GetOrganizationSubscription\{
  GetOrganizationSubscriptionHandler,
  GetOrganizationSubscriptionQuery
};
use Billing\Domain\Model\Subscription\Subscription;
use Billing\Domain\ValueObject\{BillingInterval, SubscriptionId, SubscriptionStatus};
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test GetOrganizationSubscriptionHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GetOrganizationSubscriptionHandlerTest extends TestCase
{
  private const string SUBSCRIPTION_ID = '55555555-5555-4555-8555-555555555555';

  #[Test]
  public function itResolvesTheCurrentPlanNameAndPricingWhenThereIsNoSubscriptionRow(): void
  {
    $subscriptions = $this->createStub(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn(null);

    $organizationPlan = $this->createStub(OrganizationPlanPort::class);
    $organizationPlan->method('findCurrentPlan')->willReturn(new PlanSummary(key: 'free', name: 'Free'));

    $handler = new GetOrganizationSubscriptionHandler($subscriptions, $organizationPlan, $this->priceCatalog());

    $result = $handler(new GetOrganizationSubscriptionQuery('org-1'));

    self::assertSame('org-1', $result->organizationId);
    self::assertFalse($result->hasSubscription);
    self::assertFalse($result->active);
    self::assertSame('free', $result->planKey);
    self::assertSame('Free', $result->planName);
    self::assertSame('eur', $result->currency);
    self::assertNull($result->monthlyAmount);
    self::assertNull($result->yearlyAmount);
  }

  #[Test]
  public function itResolvesTheCurrentPlanNameAndPricingForALiveSubscription(): void
  {
    $subscription = $this->activeSubscription();

    $subscriptions = $this->createStub(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn($subscription);

    $organizationPlan = $this->createStub(OrganizationPlanPort::class);
    $organizationPlan->method('findCurrentPlan')->willReturn(new PlanSummary(key: 'pro', name: 'Pro'));

    $handler = new GetOrganizationSubscriptionHandler($subscriptions, $organizationPlan, $this->priceCatalog());

    $result = $handler(new GetOrganizationSubscriptionQuery('org-1'));

    self::assertTrue($result->hasSubscription);
    self::assertTrue($result->active);
    self::assertSame('active', $result->status);
    self::assertSame('pro', $result->planKey);
    self::assertSame('Pro', $result->planName);
    self::assertSame('month', $result->interval);
    self::assertSame('eur', $result->currency);
    self::assertSame(1000, $result->monthlyAmount);
    self::assertSame(10000, $result->yearlyAmount);
  }

  #[Test]
  public function itPrefersTheOrganizationsCurrentPlanOverTheSubscriptionsStalePlanKey(): void
  {
    // A canceled subscription is downgraded to `free` by the webhook; the
    // "current plan" card must reflect that, not the last-billed plan key.
    $subscription = $this->activeSubscription();

    $subscriptions = $this->createStub(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn($subscription);

    $organizationPlan = $this->createStub(OrganizationPlanPort::class);
    $organizationPlan->method('findCurrentPlan')->willReturn(new PlanSummary(key: 'free', name: 'Free'));

    $handler = new GetOrganizationSubscriptionHandler($subscriptions, $organizationPlan, $this->priceCatalog());

    $result = $handler(new GetOrganizationSubscriptionQuery('org-1'));

    self::assertSame('free', $result->planKey);
    self::assertSame('Free', $result->planName);
    self::assertNull($result->monthlyAmount);
    self::assertNull($result->yearlyAmount);
  }

  #[Test]
  public function itLeavesThePlanNameAndPricingNullWhenTheCurrentPlanCannotBeResolved(): void
  {
    $subscriptions = $this->createStub(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn(null);

    $organizationPlan = $this->createStub(OrganizationPlanPort::class);
    $organizationPlan->method('findCurrentPlan')->willReturn(null);

    $handler = new GetOrganizationSubscriptionHandler($subscriptions, $organizationPlan, $this->priceCatalog());

    $result = $handler(new GetOrganizationSubscriptionQuery('org-1'));

    self::assertNull($result->planKey);
    self::assertNull($result->planName);
    self::assertNull($result->currency);
    self::assertNull($result->monthlyAmount);
    self::assertNull($result->yearlyAmount);
  }

  private function activeSubscription(): Subscription
  {
    $subscription = Subscription::start(
      SubscriptionId::fromString(self::SUBSCRIPTION_ID),
      'org-1',
      'cus_1',
    );
    $subscription->syncFromStripe(
      stripeSubscriptionId: 'sub_1',
      status: SubscriptionStatus::ACTIVE,
      planKey: 'pro',
      interval: BillingInterval::MONTH,
      currentPeriodEnd: new DateTimeImmutable('2026-08-01T00:00:00+00:00'),
      cancelAtPeriodEnd: false,
    );

    return $subscription;
  }

  private function priceCatalog(): BillingPriceCatalog
  {
    return new BillingPriceCatalog(
      [
        'pro' => [
          'month' => ['priceId' => 'price_pro_m', 'amount' => 1000],
          'year' => ['priceId' => 'price_pro_y', 'amount' => 10000],
        ],
      ],
      'eur',
    );
  }
}
