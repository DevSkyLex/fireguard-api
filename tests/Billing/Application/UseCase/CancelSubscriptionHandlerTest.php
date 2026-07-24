<?php

declare(strict_types=1);

namespace Tests\Billing\Application\UseCase;

use Billing\Application\Port\Outbound\{StripeGatewayPort, SubscriptionRepositoryPort};
use Billing\Application\UseCase\Command\CancelSubscription\{
  CancelSubscriptionCommand,
  CancelSubscriptionHandler
};
use Billing\Domain\Exception\NoActiveSubscriptionException;
use Billing\Domain\Model\Subscription\Subscription;
use Billing\Domain\ValueObject\{BillingInterval, SubscriptionId, SubscriptionStatus};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\TransactionManagerPort;

/**
 * Test CancelSubscriptionHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CancelSubscriptionHandlerTest extends TestCase
{
  private const string SUBSCRIPTION_ID = '33333333-3333-4333-8333-333333333333';

  #[Test]
  public function itSchedulesCancellationOnStripeAndMirrorsLocally(): void
  {
    $subscription = $this->activeSubscription();

    $stripe = $this->createMock(StripeGatewayPort::class);
    $stripe->expects(self::once())
      ->method('scheduleCancellation')
      ->with('sub_1');

    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn($subscription);
    $subscriptions->expects(self::once())->method('save')->with($subscription);

    $handler = new CancelSubscriptionHandler($subscriptions, $stripe, $this->transactionManager());

    $handler(new CancelSubscriptionCommand('org-1'));

    self::assertTrue($subscription->cancelAtPeriodEnd());
    self::assertSame(SubscriptionStatus::ACTIVE, $subscription->status());
  }

  #[Test]
  public function itThrowsWhenNoSubscriptionExists(): void
  {
    $stripe = $this->createMock(StripeGatewayPort::class);
    $stripe->expects(self::never())->method('scheduleCancellation');

    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn(null);
    $subscriptions->expects(self::never())->method('save');

    $handler = new CancelSubscriptionHandler($subscriptions, $stripe, $this->transactionManager());

    $this->expectException(NoActiveSubscriptionException::class);

    $handler(new CancelSubscriptionCommand('org-1'));
  }

  #[Test]
  public function itThrowsWhenTheSubscriptionHasNoStripeId(): void
  {
    $subscription = Subscription::start(
      SubscriptionId::fromString(self::SUBSCRIPTION_ID),
      'org-1',
      'cus_1',
    );

    $stripe = $this->createMock(StripeGatewayPort::class);
    $stripe->expects(self::never())->method('scheduleCancellation');

    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn($subscription);
    $subscriptions->expects(self::never())->method('save');

    $handler = new CancelSubscriptionHandler($subscriptions, $stripe, $this->transactionManager());

    $this->expectException(NoActiveSubscriptionException::class);

    $handler(new CancelSubscriptionCommand('org-1'));
  }

  private function activeSubscription(): Subscription
  {
    $subscription = Subscription::start(
      SubscriptionId::fromString(self::SUBSCRIPTION_ID),
      'org-1',
      'cus_1',
    );
    $subscription->syncFromStripe('sub_1', SubscriptionStatus::ACTIVE, 'pro', BillingInterval::MONTH, null, false);

    return $subscription;
  }

  private function transactionManager(): TransactionManagerPort
  {
    $manager = $this->createMock(TransactionManagerPort::class);
    $manager->method('transactional')->willReturnCallback(
      static fn (callable $operation): mixed => $operation(),
    );

    return $manager;
  }
}
