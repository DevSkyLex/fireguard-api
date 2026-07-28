<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Application\UseCase;

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
use Shared\Application\Port\Outbound\TransactionManagerPort;

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
    $stripe = $this->createStub(StripeGatewayPort::class);
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
    );

    $handler(new HandleStripeWebhookCommand('{}', 'sig'));
  }

  #[Test]
  public function aDeletedSubscriptionEventDowngradesToFree(): void
  {
    $stripe = $this->createStub(StripeGatewayPort::class);
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
    );

    $handler(new HandleStripeWebhookCommand('{}', 'sig'));
  }

  #[Test]
  public function anUnrelatedEventChangesNothing(): void
  {
    $stripe = $this->createStub(StripeGatewayPort::class);
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
    );

    $handler(new HandleStripeWebhookCommand('{}', 'sig'));
  }

  #[Test]
  public function anEventWithoutMetadataResolvesTheOrganizationThroughTheStripeCustomer(): void
  {
    $stripe = $this->createStub(StripeGatewayPort::class);
    $stripe->method('parseEvent')->willReturn(new StripeEvent(
      type: 'customer.subscription.updated',
      organizationId: null,
      customerId: 'cus_1',
      // No subscription id: the projection cannot be synchronized, so the
      // handler must stop after the lookup rather than persist a half event.
      subscriptionId: null,
      status: 'active',
      priceId: 'price_pro_m',
    ));

    $existing = Subscription::start(SubscriptionId::fromString(self::SUBSCRIPTION_ID), 'org-2', 'cus_1');

    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->expects(self::once())
      ->method('findByStripeCustomerId')
      ->with('cus_1')
      ->willReturn($existing);
    $subscriptions->expects(self::never())->method('save');

    $planAssignment = $this->createMock(OrganizationPlanAssignmentPort::class);
    $planAssignment->expects(self::never())->method('assignPlanByKey');

    $this->createHandler($stripe, $subscriptions, $planAssignment)(new HandleStripeWebhookCommand('{}', 'sig'));
  }

  #[Test]
  public function anEventForAnUnknownPriceIsIgnored(): void
  {
    $stripe = $this->createStub(StripeGatewayPort::class);
    $stripe->method('parseEvent')->willReturn(new StripeEvent(
      type: 'customer.subscription.created',
      organizationId: 'org-1',
      customerId: 'cus_1',
      subscriptionId: 'sub_1',
      status: 'active',
      priceId: 'price_not_in_catalog',
    ));

    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->expects(self::never())->method('save');

    $planAssignment = $this->createMock(OrganizationPlanAssignmentPort::class);
    $planAssignment->expects(self::never())->method('assignPlanByKey');

    $this->createHandler($stripe, $subscriptions, $planAssignment)(new HandleStripeWebhookCommand('{}', 'sig'));
  }

  #[Test]
  public function aDeletedSubscriptionEventWithNoResolvableOrganizationIsIgnored(): void
  {
    $stripe = $this->createStub(StripeGatewayPort::class);
    $stripe->method('parseEvent')->willReturn(new StripeEvent(type: 'customer.subscription.deleted'));

    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->expects(self::never())->method('findByOrganizationId');
    $subscriptions->expects(self::never())->method('save');

    $planAssignment = $this->createMock(OrganizationPlanAssignmentPort::class);
    $planAssignment->expects(self::never())->method('assignPlanByKey');

    $this->createHandler($stripe, $subscriptions, $planAssignment)(new HandleStripeWebhookCommand('{}', 'sig'));
  }

  private function createHandler(
    StripeGatewayPort $stripe,
    SubscriptionRepositoryPort $subscriptions,
    OrganizationPlanAssignmentPort $planAssignment,
  ): HandleStripeWebhookHandler {
    return new HandleStripeWebhookHandler(
      $stripe,
      $subscriptions,
      new BillingPriceCatalog($this->prices(), 'eur'),
      $planAssignment,
      $this->uuidFactory(),
      $this->transactionManager(),
    );
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
    $factory = $this->createStub(UuidFactory::class);
    $factory->method('create')->willReturn(SubscriptionId::fromString(self::SUBSCRIPTION_ID));

    return $factory;
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
