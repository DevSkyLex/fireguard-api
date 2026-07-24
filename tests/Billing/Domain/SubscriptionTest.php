<?php

declare(strict_types=1);

namespace Tests\Billing\Domain;

use Billing\Domain\Model\Subscription\Subscription;
use Billing\Domain\ValueObject\{BillingInterval, SubscriptionId, SubscriptionStatus};
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test SubscriptionTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SubscriptionTest extends TestCase
{
  private const string SUBSCRIPTION_ID = '33333333-3333-4333-8333-333333333333';

  #[Test]
  public function itStartsIncompleteWithOnlyTheCustomerLink(): void
  {
    $subscription = Subscription::start(
      SubscriptionId::fromString(self::SUBSCRIPTION_ID),
      'org-1',
      'cus_123',
    );

    self::assertSame('org-1', $subscription->organizationId());
    self::assertSame('cus_123', $subscription->stripeCustomerId());
    self::assertSame(SubscriptionStatus::INCOMPLETE, $subscription->status());
    self::assertNull($subscription->planKey());
    self::assertNull($subscription->stripeSubscriptionId());
    self::assertFalse($subscription->cancelAtPeriodEnd());
  }

  #[Test]
  public function itSynchronizesFromStripe(): void
  {
    $subscription = Subscription::start(
      SubscriptionId::fromString(self::SUBSCRIPTION_ID),
      'org-1',
      'cus_123',
    );
    $periodEnd = new DateTimeImmutable('2026-07-20T00:00:00+00:00');

    $subscription->syncFromStripe(
      stripeSubscriptionId: 'sub_456',
      status: SubscriptionStatus::ACTIVE,
      planKey: 'pro',
      interval: BillingInterval::MONTH,
      currentPeriodEnd: $periodEnd,
      cancelAtPeriodEnd: true,
    );

    self::assertSame('sub_456', $subscription->stripeSubscriptionId());
    self::assertSame(SubscriptionStatus::ACTIVE, $subscription->status());
    self::assertSame('pro', $subscription->planKey());
    self::assertSame(BillingInterval::MONTH, $subscription->interval());
    self::assertSame($periodEnd, $subscription->currentPeriodEnd());
    self::assertTrue($subscription->cancelAtPeriodEnd());
  }

  #[Test]
  public function itMarksCanceled(): void
  {
    $subscription = Subscription::start(
      SubscriptionId::fromString(self::SUBSCRIPTION_ID),
      'org-1',
      'cus_123',
    );

    $subscription->markCanceled();

    self::assertSame(SubscriptionStatus::CANCELED, $subscription->status());
    self::assertFalse($subscription->cancelAtPeriodEnd());
  }

  #[Test]
  public function itSchedulesAndResumesCancellationWithoutTouchingStatus(): void
  {
    $subscription = Subscription::start(
      SubscriptionId::fromString(self::SUBSCRIPTION_ID),
      'org-1',
      'cus_123',
    );
    $subscription->syncFromStripe(
      stripeSubscriptionId: 'sub_456',
      status: SubscriptionStatus::ACTIVE,
      planKey: 'pro',
      interval: BillingInterval::MONTH,
      currentPeriodEnd: new DateTimeImmutable('2026-07-20T00:00:00+00:00'),
      cancelAtPeriodEnd: false,
    );

    $subscription->scheduleCancellation();

    self::assertTrue($subscription->cancelAtPeriodEnd());
    self::assertSame(SubscriptionStatus::ACTIVE, $subscription->status());

    $subscription->resumeCancellation();

    self::assertFalse($subscription->cancelAtPeriodEnd());
    self::assertSame(SubscriptionStatus::ACTIVE, $subscription->status());
  }

  #[Test]
  public function statusGrantsAccessOnlyWhileUsable(): void
  {
    self::assertTrue(SubscriptionStatus::ACTIVE->grantsAccess());
    self::assertTrue(SubscriptionStatus::TRIALING->grantsAccess());
    self::assertTrue(SubscriptionStatus::PAST_DUE->grantsAccess());
    self::assertFalse(SubscriptionStatus::CANCELED->grantsAccess());
    self::assertFalse(SubscriptionStatus::UNPAID->grantsAccess());
    self::assertSame(SubscriptionStatus::INCOMPLETE, SubscriptionStatus::fromStripe('weird'));
  }
}
