<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Application\UseCase;

use Billing\Application\Port\Outbound\{StripeGatewayPort, SubscriptionRepositoryPort};
use Billing\Application\UseCase\Command\ResumeSubscription\{
  ResumeSubscriptionCommand,
  ResumeSubscriptionHandler
};
use Billing\Domain\Exception\NoActiveSubscriptionException;
use Billing\Domain\Model\Subscription\Subscription;
use Billing\Domain\ValueObject\{BillingInterval, SubscriptionId, SubscriptionStatus};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\TransactionManagerPort;

/**
 * Test ResumeSubscriptionHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ResumeSubscriptionHandlerTest extends TestCase
{
  private const string SUBSCRIPTION_ID = '33333333-3333-4333-8333-333333333333';

  #[Test]
  public function itClearsCancellationOnStripeAndMirrorsLocally(): void
  {
    $subscription = $this->cancelingSubscription();

    $stripe = $this->createMock(StripeGatewayPort::class);
    $stripe->expects(self::once())
      ->method('resumeCancellation')
      ->with('sub_1');

    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn($subscription);
    $subscriptions->expects(self::once())->method('save')->with($subscription);

    $handler = new ResumeSubscriptionHandler($subscriptions, $stripe, $this->transactionManager());

    $handler(new ResumeSubscriptionCommand('org-1'));

    self::assertFalse($subscription->cancelAtPeriodEnd());
    self::assertSame(SubscriptionStatus::ACTIVE, $subscription->status());
  }

  #[Test]
  public function itThrowsWhenNoSubscriptionExists(): void
  {
    $stripe = $this->createMock(StripeGatewayPort::class);
    $stripe->expects(self::never())->method('resumeCancellation');

    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn(null);
    $subscriptions->expects(self::never())->method('save');

    $handler = new ResumeSubscriptionHandler($subscriptions, $stripe, $this->transactionManager());

    $this->expectException(NoActiveSubscriptionException::class);

    $handler(new ResumeSubscriptionCommand('org-1'));
  }

  private function cancelingSubscription(): Subscription
  {
    $subscription = Subscription::start(
      SubscriptionId::fromString(self::SUBSCRIPTION_ID),
      'org-1',
      'cus_1',
    );
    $subscription->syncFromStripe('sub_1', SubscriptionStatus::ACTIVE, 'pro', BillingInterval::MONTH, null, true);

    return $subscription;
  }

  private function transactionManager(): TransactionManagerPort
  {
    $manager = $this->createStub(TransactionManagerPort::class);
    $manager->method('transactional')->willReturnCallback(
      static fn (callable $operation): mixed => $operation(),
    );

    return $manager;
  }
}
