<?php

declare(strict_types=1);

namespace Tests\Billing\Application\UseCase;

use Billing\Application\Contract\Stripe\StripeEvent;
use Billing\Application\Port\Outbound\{
  OrganizationPlanAssignmentPort,
  StripeGatewayPort,
  SubscriptionRepositoryPort
};
use Billing\Application\Service\BillingPriceCatalog;
use Billing\Application\UseCase\Command\HandleStripeWebhook\{
  HandleStripeWebhookCommand,
  HandleStripeWebhookHandler
};
use Billing\Domain\Model\Subscription\Subscription;
use Billing\Domain\ValueObject\SubscriptionId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{LoggerPort, TransactionManagerPort};

/**
 * Test HandleStripeWebhookHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class HandleStripeWebhookHandlerTest extends TestCase
{
  private const string SUBSCRIPTION_ID = '33333333-3333-4333-8333-333333333333';

  #[Test]
  public function anActiveSubscriptionEventAssignsThePaidPlan(): void
  {
    $stripe = $this->createMock(StripeGatewayPort::class);
    $stripe->method('parseEvent')->willReturn(new StripeEvent(
      type: 'customer.subscription.updated',
      organizationId: 'org-1',
      customerId: 'cus_1',
      subscriptionId: 'sub_1',
      status: 'active',
      priceId: 'price_pro_m',
      currentPeriodEnd: 1_800_000_000,
      cancelAtPeriodEnd: false,
    ));

    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn(null);
    $subscriptions->expects(self::once())->method('save');

    $planAssignment = $this->createMock(OrganizationPlanAssignmentPort::class);
    $planAssignment->expects(self::once())
      ->method('assignPlanByKey')
      ->with('org-1', 'pro');

    $handler = new HandleStripeWebhookHandler(
      $stripe,
      $subscriptions,
      new BillingPriceCatalog($this->prices(), 'eur'),
      $planAssignment,
      $this->uuidFactory(),
      $this->transactionManager(),
      $this->createStub(LoggerPort::class),
    );

    $handler(new HandleStripeWebhookCommand('{}', 'sig'));
  }

  #[Test]
  public function aDeletedSubscriptionEventDowngradesToFree(): void
  {
    $stripe = $this->createMock(StripeGatewayPort::class);
    $stripe->method('parseEvent')->willReturn(new StripeEvent(
      type: 'customer.subscription.deleted',
      organizationId: 'org-1',
      customerId: 'cus_1',
    ));

    $existing = Subscription::start(
      SubscriptionId::fromString(self::SUBSCRIPTION_ID),
      'org-1',
      'cus_1',
    );

    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn($existing);
    $subscriptions->expects(self::once())->method('save');

    $planAssignment = $this->createMock(OrganizationPlanAssignmentPort::class);
    $planAssignment->expects(self::once())
      ->method('assignPlanByKey')
      ->with('org-1', 'free');

    $handler = new HandleStripeWebhookHandler(
      $stripe,
      $subscriptions,
      new BillingPriceCatalog($this->prices(), 'eur'),
      $planAssignment,
      $this->uuidFactory(),
      $this->transactionManager(),
      $this->createStub(LoggerPort::class),
    );

    $handler(new HandleStripeWebhookCommand('{}', 'sig'));
  }

  #[Test]
  public function anUnrelatedEventChangesNothing(): void
  {
    $stripe = $this->createMock(StripeGatewayPort::class);
    $stripe->method('parseEvent')->willReturn(new StripeEvent(type: 'invoice.paid'));

    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->expects(self::never())->method('save');

    $planAssignment = $this->createMock(OrganizationPlanAssignmentPort::class);
    $planAssignment->expects(self::never())->method('assignPlanByKey');

    $handler = new HandleStripeWebhookHandler(
      $stripe,
      $subscriptions,
      new BillingPriceCatalog($this->prices(), 'eur'),
      $planAssignment,
      $this->uuidFactory(),
      $this->transactionManager(),
      $this->createStub(LoggerPort::class),
    );

    $handler(new HandleStripeWebhookCommand('{}', 'sig'));
  }

  /**
   * @return array<string, mixed>
   */
  private function prices(): array
  {
    return [
      'pro' => [
        'month' => ['priceId' => 'price_pro_m', 'amount' => 1000],
        'year' => ['priceId' => 'price_pro_y', 'amount' => 10000],
      ],
    ];
  }

  private function uuidFactory(): UuidFactory
  {
    $factory = $this->createMock(UuidFactory::class);
    $factory->method('create')->willReturn(SubscriptionId::fromString(self::SUBSCRIPTION_ID));

    return $factory;
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
