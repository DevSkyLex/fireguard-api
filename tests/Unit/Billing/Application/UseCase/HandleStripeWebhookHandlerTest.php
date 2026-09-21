<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Application\UseCase;

use Billing\Application\Contract\Stripe\{StripeEvent, StripeSubscription};
use Billing\Application\Port\Outbound\{OrganizationPlanAssignmentPort, StripeGatewayPort, SubscriptionRepositoryPort};
use Billing\Application\Service\BillingPriceCatalog;
use Billing\Application\UseCase\Command\HandleStripeWebhook\{HandleStripeWebhookCommand, HandleStripeWebhookHandler};
use Billing\Domain\Model\Subscription\Subscription;
use Billing\Domain\ValueObject\{BillingInterval, SubscriptionId, SubscriptionStatus};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\LoggerPort;
use Tests\Support\Billing\ImmediateBillingReconciliation;

/**
 * Tests ordered, repeated and obsolete Stripe deliveries against fresh state.
 *
 * @category UseCase Tests
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class HandleStripeWebhookHandlerTest extends TestCase
{
  private const string ID = '33333333-3333-4333-8333-333333333333';

  #[Test]
  public function aFirstEventUsesCurrentRemoteStateRatherThanTheDeliveredSnapshot(): void
  {
    $event = $this->event(status: 'canceled', type: 'customer.subscription.deleted');
    $stripe = $this->gateway($event, [$this->remote('sub_new', 'active', 20)]);
    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->expects(self::once())->method('save')->with(self::callback(static function (Subscription $subscription): bool {
      self::assertSame('sub_new', $subscription->stripeSubscriptionId());
      self::assertSame(SubscriptionStatus::ACTIVE, $subscription->status());
      self::assertFalse($subscription->cancelAtPeriodEnd());

      return true;
    }));
    $plans = $this->createMock(OrganizationPlanAssignmentPort::class);
    $plans->expects(self::once())->method('assignPlanByKey')->with('org-1', 'pro');

    $this->handler($stripe, $subscriptions, $plans)(new HandleStripeWebhookCommand('{}', 'signature'));
  }

  #[Test]
  public function anOldCancellationNeverDowngradesANewerSubscription(): void
  {
    $stripe = $this->gateway($this->event(type: 'customer.subscription.deleted'), [
      $this->remote('sub_old', 'canceled', 10),
      $this->remote('sub_new', 'active', 20),
      $this->remote('sub_abandoned', 'incomplete', 30),
    ]);
    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn($this->existing('sub_new'));
    $subscriptions->expects(self::once())->method('save')->with(self::callback(static fn (Subscription $subscription): bool => 'sub_new' === $subscription->stripeSubscriptionId()));
    $plans = $this->createMock(OrganizationPlanAssignmentPort::class);
    $plans->expects(self::once())->method('assignPlanByKey')->with('org-1', 'pro');

    $this->handler($stripe, $subscriptions, $plans)(new HandleStripeWebhookCommand('{}', 'signature'));
  }

  #[Test]
  public function aConfirmedRemoteCancellationDowngradesToFree(): void
  {
    $stripe = $this->gateway($this->event(), [$this->remote('sub_old', 'canceled', 10)]);
    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn($this->existing());
    $subscriptions->expects(self::once())->method('save')->with(self::callback(static fn (Subscription $subscription): bool => SubscriptionStatus::CANCELED === $subscription->status()));
    $plans = $this->createMock(OrganizationPlanAssignmentPort::class);
    $plans->expects(self::once())->method('assignPlanByKey')->with('org-1', 'free');

    $this->handler($stripe, $subscriptions, $plans)(new HandleStripeWebhookCommand('{}', 'signature'));
  }

  #[Test]
  public function duplicateEventsHaveNoRepeatedLocalConsequencesOrStripeRead(): void
  {
    $stripe = $this->createMock(StripeGatewayPort::class);
    $stripe->method('parseEvent')->willReturn($this->event());
    $stripe->expects(self::once())->method('listSubscriptions')->willReturn([$this->remote()]);
    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->expects(self::once())->method('save');
    $plans = $this->createMock(OrganizationPlanAssignmentPort::class);
    $plans->expects(self::once())->method('assignPlanByKey');
    $handler = $this->handler($stripe, $subscriptions, $plans);

    $handler(new HandleStripeWebhookCommand('{}', 'signature'));
    $handler(new HandleStripeWebhookCommand('{}', 'signature'));
  }

  #[Test]
  #[DataProvider('rejectedEvents')]
  public function unrelatedMalformedAndWrongEnvironmentEventsDoNotMutate(StripeEvent $event): void
  {
    $stripe = $this->createMock(StripeGatewayPort::class);
    $stripe->method('parseEvent')->willReturn($event);
    $stripe->expects(self::never())->method('listSubscriptions');
    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->expects(self::never())->method('save');
    $plans = $this->createMock(OrganizationPlanAssignmentPort::class);
    $plans->expects(self::never())->method('assignPlanByKey');

    $this->handler($stripe, $subscriptions, $plans)(new HandleStripeWebhookCommand('{}', 'signature'));
  }

  /**
   * @return iterable<string, array{StripeEvent}>
   */
  public static function rejectedEvents(): iterable
  {
    yield 'unrelated' => [new StripeEvent(type: 'invoice.paid')];
    yield 'missing identifiers' => [new StripeEvent(type: 'customer.subscription.updated')];
    yield 'live event with test key' => [new StripeEvent(type: 'customer.subscription.updated', organizationId: 'org-1', customerId: 'cus_1', subscriptionId: 'sub_1', eventId: 'evt_live', liveMode: true)];
    yield 'no organization' => [new StripeEvent(type: 'customer.subscription.deleted', customerId: 'cus_unknown', subscriptionId: 'sub_1', eventId: 'evt_unknown')];
  }

  #[Test]
  #[DataProvider('subscriptionEventTypes')]
  public function contradictoryMetadataCannotTargetAnotherOrganization(string $type): void
  {
    $stripe = $this->createMock(StripeGatewayPort::class);
    $stripe->method('parseEvent')->willReturn($this->event(type: $type));
    $stripe->expects(self::never())->method('listSubscriptions');
    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByStripeCustomerId')->willReturn(Subscription::start(new SubscriptionId(self::ID), 'org-other', 'cus_1'));
    $subscriptions->expects(self::never())->method('save');
    $plans = $this->createMock(OrganizationPlanAssignmentPort::class);
    $plans->expects(self::never())->method('assignPlanByKey');

    $this->handler($stripe, $subscriptions, $plans)(new HandleStripeWebhookCommand('{}', 'signature'));
  }

  /**
   * @return iterable<array{string}>
   */
  public static function subscriptionEventTypes(): iterable
  {
    yield ['customer.subscription.created'];
    yield ['customer.subscription.updated'];
    yield ['customer.subscription.deleted'];
  }

  #[Test]
  public function metadataCannotReplaceAnOrganizationsExistingCustomer(): void
  {
    $stripe = $this->createMock(StripeGatewayPort::class);
    $stripe->method('parseEvent')->willReturn($this->event());
    $stripe->expects(self::never())->method('listSubscriptions');
    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn(Subscription::start(new SubscriptionId(self::ID), 'org-1', 'cus_other'));
    $subscriptions->expects(self::never())->method('save');
    $plans = $this->createMock(OrganizationPlanAssignmentPort::class);
    $plans->expects(self::never())->method('assignPlanByKey');

    $this->handler($stripe, $subscriptions, $plans)(new HandleStripeWebhookCommand('{}', 'signature'));
  }

  #[Test]
  public function absentMetadataUsesTheLocalCustomerMapping(): void
  {
    $stripe = $this->gateway(new StripeEvent(type: 'customer.subscription.updated', customerId: 'cus_1', subscriptionId: 'sub_old', eventId: 'evt_1'), [$this->remote()]);
    $subscriptions = $this->createStub(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByStripeCustomerId')->willReturn($this->existing());
    $subscriptions->method('findByOrganizationId')->willReturn($this->existing());
    $plans = $this->createMock(OrganizationPlanAssignmentPort::class);
    $plans->expects(self::once())->method('assignPlanByKey')->with('org-1', 'pro');

    $this->handler($stripe, $subscriptions, $plans)(new HandleStripeWebhookCommand('{}', 'signature'));
  }

  #[Test]
  public function aFailedCurrentReadIsRetryableAndNeverDowngrades(): void
  {
    $stripe = $this->createStub(StripeGatewayPort::class);
    $stripe->method('parseEvent')->willReturn($this->event(type: 'customer.subscription.deleted'));
    $stripe->method('listSubscriptions')->willThrowException(new RuntimeException('Stripe unavailable'));
    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->expects(self::never())->method('save');
    $plans = $this->createMock(OrganizationPlanAssignmentPort::class);
    $plans->expects(self::never())->method('assignPlanByKey');
    $journal = new ImmediateBillingReconciliation();

    try {
      $this->handler($stripe, $subscriptions, $plans, $journal)(new HandleStripeWebhookCommand('{}', 'signature'));
      self::fail('Remote failure must propagate to Stripe for retry.');
    } catch (RuntimeException $exception) {
      self::assertSame('Stripe unavailable', $exception->getMessage());
    }
    self::assertSame([], $journal->processed);
  }

  #[Test]
  public function anUnknownCurrentPriceIsNotAcknowledgedAsReconciled(): void
  {
    $remote = new StripeSubscription('sub_old', 'cus_1', 'org-1', 'active', 'price_unknown', null, false, 10, false);
    $stripe = $this->gateway($this->event(), [$remote]);
    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->expects(self::never())->method('save');
    $plans = $this->createMock(OrganizationPlanAssignmentPort::class);
    $plans->expects(self::never())->method('assignPlanByKey');
    $journal = new ImmediateBillingReconciliation();

    try {
      $this->handler($stripe, $subscriptions, $plans, $journal)(new HandleStripeWebhookCommand('{}', 'signature'));
      self::fail('A catalog mismatch must remain retryable.');
    } catch (RuntimeException $exception) {
      self::assertStringContainsString('not configured', $exception->getMessage());
    }
    self::assertSame([], $journal->processed);
  }

  private function event(string $type = 'customer.subscription.updated', string $status = 'active'): StripeEvent
  {
    return new StripeEvent(type: $type, organizationId: 'org-1', customerId: 'cus_1', subscriptionId: 'sub_old', status: $status, eventId: 'evt_1', created: 5);
  }

  private function remote(string $id = 'sub_old', string $status = 'active', int $created = 10): StripeSubscription
  {
    return new StripeSubscription($id, 'cus_1', 'org-1', $status, 'price_pro_m', 1_800_000_000, false, $created, false);
  }

  private function existing(string $id = 'sub_old'): Subscription
  {
    $subscription = Subscription::start(new SubscriptionId(self::ID), 'org-1', 'cus_1');
    $subscription->syncFromStripe($id, SubscriptionStatus::ACTIVE, 'pro', BillingInterval::MONTH, null, false);

    return $subscription;
  }

  /**
   * @param list<StripeSubscription> $current
   */
  private function gateway(StripeEvent $event, array $current): StripeGatewayPort
  {
    $stripe = $this->createStub(StripeGatewayPort::class);
    $stripe->method('parseEvent')->willReturn($event);
    $stripe->method('listSubscriptions')->willReturn($current);

    return $stripe;
  }

  private function handler(StripeGatewayPort $stripe, SubscriptionRepositoryPort $subscriptions, OrganizationPlanAssignmentPort $plans, ?ImmediateBillingReconciliation $journal = null): HandleStripeWebhookHandler
  {
    $uuid = $this->createStub(UuidFactory::class);
    $uuid->method('create')->willReturn(new SubscriptionId(self::ID));

    return new HandleStripeWebhookHandler($stripe, $subscriptions, new BillingPriceCatalog(['pro' => ['month' => ['priceId' => 'price_pro_m', 'amount' => 1000]]], 'eur'), $plans, $uuid, $journal ?? new ImmediateBillingReconciliation(), $this->createStub(LoggerPort::class));
  }
}
