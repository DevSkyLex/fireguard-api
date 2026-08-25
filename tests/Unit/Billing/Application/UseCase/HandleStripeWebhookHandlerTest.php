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
  public function anEventWhoseMetadataContradictsTheLocalMappingIsIgnored(): void
  {
    // The metadata lives on Stripe's side and rides every later event for the
    // customer. Trusting it over the mapping we control would let a
    // mislabelled customer act on an organization that never subscribed.
    $stripe = $this->createStub(StripeGatewayPort::class);
    $stripe->method('parseEvent')->willReturn(new StripeEvent(
      type: 'customer.subscription.updated',
      organizationId: 'org-victim',
      customerId: 'cus_1',
      subscriptionId: 'sub_1',
      status: 'active',
      priceId: 'price_pro_m',
      currentPeriodEnd: 1_800_000_000,
      cancelAtPeriodEnd: false,
    ));

    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByStripeCustomerId')
      ->willReturn(Subscription::start(
        new SubscriptionId(self::SUBSCRIPTION_ID),
        'org-attacker',
        'cus_1',
      ));
    $subscriptions->expects(self::never())->method('save');

    $planAssignment = $this->createMock(OrganizationPlanAssignmentPort::class);
    $planAssignment->expects(self::never())->method('assignPlanByKey');

    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())->method('warning');

    $handler = new HandleStripeWebhookHandler(
      $stripe,
      $subscriptions,
      new BillingPriceCatalog($this->prices(), 'eur'),
      $planAssignment,
      $this->uuidFactory(),
      $this->transactionManager(),
      $logger,
    );

    $handler(new HandleStripeWebhookCommand('{}', 'sig'));
  }

  #[Test]
  public function aCancellationWhoseMetadataContradictsTheLocalMappingDoesNotDowngradeAnyone(): void
  {
    // The sharpest edge of the same defect: this branch assigns the free plan
    // to whatever organization it resolves to.
    $stripe = $this->createStub(StripeGatewayPort::class);
    $stripe->method('parseEvent')->willReturn(new StripeEvent(
      type: 'customer.subscription.deleted',
      organizationId: 'org-victim',
      customerId: 'cus_1',
      subscriptionId: 'sub_1',
      status: 'canceled',
      priceId: 'price_pro_m',
      currentPeriodEnd: 1_800_000_000,
      cancelAtPeriodEnd: true,
    ));

    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByStripeCustomerId')
      ->willReturn(Subscription::start(
        new SubscriptionId(self::SUBSCRIPTION_ID),
        'org-attacker',
        'cus_1',
      ));
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

  #[Test]
  public function aFirstEventForAnUnknownCustomerStillTrustsItsMetadata(): void
  {
    // Nothing local contradicts it yet: this is how a subscription is born.
    $stripe = $this->createStub(StripeGatewayPort::class);
    $stripe->method('parseEvent')->willReturn(new StripeEvent(
      type: 'customer.subscription.updated',
      organizationId: 'org-1',
      customerId: 'cus_new',
      subscriptionId: 'sub_1',
      status: 'active',
      priceId: 'price_pro_m',
      currentPeriodEnd: 1_800_000_000,
      cancelAtPeriodEnd: false,
    ));

    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByStripeCustomerId')->willReturn(null);
    $subscriptions->method('findByOrganizationId')->willReturn(null);
    $subscriptions->expects(self::once())->method('save');

    $planAssignment = $this->createMock(OrganizationPlanAssignmentPort::class);
    $planAssignment->expects(self::once())->method('assignPlanByKey')->with('org-1', 'pro');

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
      $this->createStub(LoggerPort::class),
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
      $this->createStub(LoggerPort::class),
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
      $this->createStub(LoggerPort::class),
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
      $this->createStub(LoggerPort::class),
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
