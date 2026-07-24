<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Domain\Model\Subscription;

use Billing\Domain\Model\Subscription\Subscription;
use Billing\Domain\ValueObject\{BillingInterval, SubscriptionId, SubscriptionStatus};
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test SubscriptionTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(Subscription::class)]
final class SubscriptionTest extends TestCase
{
  private const string SUBSCRIPTION_ID = '44444444-4444-4444-8444-444444444444';

  #[Test]
  public function itStartsIncompleteWithMatchingTimestamps(): void
  {
    $id = SubscriptionId::fromString(self::SUBSCRIPTION_ID);

    $subscription = Subscription::start($id, 'org-9', 'cus_start');

    self::assertSame($id, $subscription->id());
    self::assertSame('org-9', $subscription->organizationId());
    self::assertSame('cus_start', $subscription->stripeCustomerId());
    self::assertSame(SubscriptionStatus::INCOMPLETE, $subscription->status());
    self::assertNull($subscription->stripeSubscriptionId());
    self::assertNull($subscription->planKey());
    self::assertNull($subscription->interval());
    self::assertNull($subscription->currentPeriodEnd());
    self::assertFalse($subscription->cancelAtPeriodEnd());
    self::assertEquals($subscription->createdAt(), $subscription->updatedAt());
  }

  #[Test]
  public function itReconstitutesEveryFieldFromPersistedState(): void
  {
    $id = SubscriptionId::fromString(self::SUBSCRIPTION_ID);
    $createdAt = new DateTimeImmutable('2026-01-05T09:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-02-10T12:30:00+00:00');
    $periodEnd = new DateTimeImmutable('2026-03-01T00:00:00+00:00');

    $subscription = Subscription::reconstitute(
      id: $id,
      organizationId: 'org-42',
      stripeCustomerId: 'cus_persisted',
      status: SubscriptionStatus::ACTIVE,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      stripeSubscriptionId: 'sub_persisted',
      planKey: 'enterprise',
      interval: BillingInterval::YEAR,
      currentPeriodEnd: $periodEnd,
      cancelAtPeriodEnd: true,
    );

    self::assertSame($id, $subscription->id());
    self::assertSame('org-42', $subscription->organizationId());
    self::assertSame('cus_persisted', $subscription->stripeCustomerId());
    self::assertSame(SubscriptionStatus::ACTIVE, $subscription->status());
    self::assertSame($createdAt, $subscription->createdAt());
    self::assertSame($updatedAt, $subscription->updatedAt());
    self::assertSame('sub_persisted', $subscription->stripeSubscriptionId());
    self::assertSame('enterprise', $subscription->planKey());
    self::assertSame(BillingInterval::YEAR, $subscription->interval());
    self::assertSame($periodEnd, $subscription->currentPeriodEnd());
    self::assertTrue($subscription->cancelAtPeriodEnd());
  }

  #[Test]
  public function itReconstitutesWithDefaultOptionalFields(): void
  {
    $createdAt = new DateTimeImmutable('2026-01-05T09:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-01-05T09:00:00+00:00');

    $subscription = Subscription::reconstitute(
      id: SubscriptionId::fromString(self::SUBSCRIPTION_ID),
      organizationId: 'org-42',
      stripeCustomerId: 'cus_persisted',
      status: SubscriptionStatus::PAUSED,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
    );

    self::assertNull($subscription->stripeSubscriptionId());
    self::assertNull($subscription->planKey());
    self::assertNull($subscription->interval());
    self::assertNull($subscription->currentPeriodEnd());
    self::assertFalse($subscription->cancelAtPeriodEnd());
    self::assertSame(SubscriptionStatus::PAUSED, $subscription->status());
  }

  #[Test]
  public function itSynchronizesFromStripeWithNullCadenceAndPeriod(): void
  {
    $subscription = Subscription::reconstitute(
      id: SubscriptionId::fromString(self::SUBSCRIPTION_ID),
      organizationId: 'org-42',
      stripeCustomerId: 'cus_persisted',
      status: SubscriptionStatus::INCOMPLETE,
      createdAt: new DateTimeImmutable('2020-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2020-01-01T00:00:00+00:00'),
    );

    $subscription->syncFromStripe(
      stripeSubscriptionId: 'sub_sync',
      status: SubscriptionStatus::PAST_DUE,
      planKey: 'starter',
      interval: null,
      currentPeriodEnd: null,
      cancelAtPeriodEnd: false,
    );

    self::assertSame('sub_sync', $subscription->stripeSubscriptionId());
    self::assertSame(SubscriptionStatus::PAST_DUE, $subscription->status());
    self::assertSame('starter', $subscription->planKey());
    self::assertNull($subscription->interval());
    self::assertNull($subscription->currentPeriodEnd());
    self::assertFalse($subscription->cancelAtPeriodEnd());
    self::assertGreaterThan(
      new DateTimeImmutable('2020-01-01T00:00:00+00:00'),
      $subscription->updatedAt(),
    );
  }

  #[Test]
  public function itSchedulesCancellationAndTouchesTimestamp(): void
  {
    $stale = new DateTimeImmutable('2020-01-01T00:00:00+00:00');
    $subscription = $this->reconstituteActiveAt($stale);

    $subscription->scheduleCancellation();

    self::assertTrue($subscription->cancelAtPeriodEnd());
    self::assertSame(SubscriptionStatus::ACTIVE, $subscription->status());
    self::assertGreaterThan($stale, $subscription->updatedAt());
  }

  #[Test]
  public function itResumesCancellationAndTouchesTimestamp(): void
  {
    $stale = new DateTimeImmutable('2020-01-01T00:00:00+00:00');
    $subscription = $this->reconstituteActiveAt($stale, cancelAtPeriodEnd: true);

    $subscription->resumeCancellation();

    self::assertFalse($subscription->cancelAtPeriodEnd());
    self::assertSame(SubscriptionStatus::ACTIVE, $subscription->status());
    self::assertGreaterThan($stale, $subscription->updatedAt());
  }

  #[Test]
  public function itMarksCanceledClearingScheduledCancellation(): void
  {
    $stale = new DateTimeImmutable('2020-01-01T00:00:00+00:00');
    $subscription = $this->reconstituteActiveAt($stale, cancelAtPeriodEnd: true);

    $subscription->markCanceled();

    self::assertSame(SubscriptionStatus::CANCELED, $subscription->status());
    self::assertFalse($subscription->cancelAtPeriodEnd());
    self::assertGreaterThan($stale, $subscription->updatedAt());
  }

  private function reconstituteActiveAt(
    DateTimeImmutable $timestamp,
    bool $cancelAtPeriodEnd = false,
  ): Subscription {
    return Subscription::reconstitute(
      id: SubscriptionId::fromString(self::SUBSCRIPTION_ID),
      organizationId: 'org-42',
      stripeCustomerId: 'cus_persisted',
      status: SubscriptionStatus::ACTIVE,
      createdAt: $timestamp,
      updatedAt: $timestamp,
      stripeSubscriptionId: 'sub_persisted',
      planKey: 'pro',
      interval: BillingInterval::MONTH,
      currentPeriodEnd: new DateTimeImmutable('2026-07-20T00:00:00+00:00'),
      cancelAtPeriodEnd: $cancelAtPeriodEnd,
    );
  }
}
