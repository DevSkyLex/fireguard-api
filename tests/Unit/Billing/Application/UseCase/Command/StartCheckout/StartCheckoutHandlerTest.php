<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Application\UseCase\Command\StartCheckout;

use Billing\Application\Port\Outbound\{StripeGatewayPort, SubscriptionRepositoryPort};
use Billing\Application\Service\BillingPriceCatalog;
use Billing\Application\UseCase\Command\StartCheckout\{
  StartCheckoutCommand,
  StartCheckoutHandler,
  StartCheckoutResult
};
use Billing\Domain\Model\Subscription\Subscription;
use Billing\Domain\ValueObject\{SubscriptionId, SubscriptionStatus};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test StartCheckoutHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class StartCheckoutHandlerTest extends TestCase
{
  private const string SUBSCRIPTION_ID = '55555555-5555-4555-8555-555555555555';

  private const string FRONTEND_URL = 'https://app.fireguard.test/';

  private const string SUCCESS_URL = 'https://app.fireguard.test/organizations/org-42/settings?tab=subscription&checkout=success';

  private const string CANCEL_URL = 'https://app.fireguard.test/organizations/org-42/settings?tab=subscription&checkout=cancel';

  #[Test]
  public function itCreatesTheSubscriptionLinkAndOpensCheckoutWhenNoneExists(): void
  {
    $subscriptionId = SubscriptionId::fromString(self::SUBSCRIPTION_ID);

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(SubscriptionId::class)
      ->willReturn($subscriptionId);

    $stripe = $this->createMock(StripeGatewayPort::class);
    $stripe->expects(self::once())
      ->method('ensureCustomer')
      ->with('org-42', null)
      ->willReturn('cus_new');
    $stripe->expects(self::once())
      ->method('createCheckoutSession')
      ->with('cus_new', 'price_pro_month', 'org-42', 'pro', self::SUCCESS_URL, self::CANCEL_URL)
      ->willReturn('https://checkout.stripe.test/c/session_123');

    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn(null);
    $subscriptions->expects(self::once())
      ->method('save')
      ->with(self::callback(static function (Subscription $subscription) use ($subscriptionId): bool {
        self::assertSame($subscriptionId, $subscription->id());
        self::assertSame('org-42', $subscription->organizationId());
        self::assertSame('cus_new', $subscription->stripeCustomerId());
        self::assertSame(SubscriptionStatus::INCOMPLETE, $subscription->status());

        return true;
      }));

    $handler = new StartCheckoutHandler(
      $this->priceCatalog('pro', 'month', 'price_pro_month'),
      $stripe,
      $subscriptions,
      $uuidFactory,
      $this->transactionManager(),
      self::FRONTEND_URL,
    );

    $result = $handler(new StartCheckoutCommand('org-42', 'pro', 'month'));

    self::assertInstanceOf(StartCheckoutResult::class, $result);
    self::assertSame('https://checkout.stripe.test/c/session_123', $result->url);
  }

  #[Test]
  public function itReusesTheExistingCustomerAndDoesNotPersistWhenSubscriptionExists(): void
  {
    $subscription = Subscription::start(
      SubscriptionId::fromString(self::SUBSCRIPTION_ID),
      'org-42',
      'cus_existing',
    );

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::never())->method('create');

    $stripe = $this->createMock(StripeGatewayPort::class);
    $stripe->expects(self::once())
      ->method('ensureCustomer')
      ->with('org-42', 'cus_existing')
      ->willReturn('cus_existing');
    $stripe->expects(self::once())
      ->method('createCheckoutSession')
      ->with('cus_existing', 'price_max_year', 'org-42', 'max', self::SUCCESS_URL, self::CANCEL_URL)
      ->willReturn('https://checkout.stripe.test/c/session_456');

    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn($subscription);
    $subscriptions->expects(self::never())->method('save');

    $handler = new StartCheckoutHandler(
      $this->priceCatalog('max', 'year', 'price_max_year'),
      $stripe,
      $subscriptions,
      $uuidFactory,
      $this->transactionManager(),
      self::FRONTEND_URL,
    );

    $result = $handler(new StartCheckoutCommand('org-42', 'max', 'year'));

    self::assertSame('https://checkout.stripe.test/c/session_456', $result->url);
  }

  #[Test]
  public function itThrowsWhenTheBillingIntervalIsUnsupported(): void
  {
    $stripe = $this->createMock(StripeGatewayPort::class);
    $stripe->expects(self::never())->method('ensureCustomer');
    $stripe->expects(self::never())->method('createCheckoutSession');

    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->expects(self::never())->method('findByOrganizationId');
    $subscriptions->expects(self::never())->method('save');

    $handler = new StartCheckoutHandler(
      $this->priceCatalog('pro', 'month', 'price_pro_month'),
      $stripe,
      $subscriptions,
      $this->createStub(UuidFactory::class),
      $this->transactionManager(),
      self::FRONTEND_URL,
    );

    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Unsupported billing interval.');

    $handler(new StartCheckoutCommand('org-42', 'pro', 'weekly'));
  }

  #[Test]
  public function itThrowsWhenThePlanIsNotAvailableForPurchase(): void
  {
    $stripe = $this->createMock(StripeGatewayPort::class);
    $stripe->expects(self::never())->method('ensureCustomer');
    $stripe->expects(self::never())->method('createCheckoutSession');

    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->expects(self::never())->method('findByOrganizationId');
    $subscriptions->expects(self::never())->method('save');

    $handler = new StartCheckoutHandler(
      $this->priceCatalog('pro', 'month', 'price_pro_month'),
      $stripe,
      $subscriptions,
      $this->createStub(UuidFactory::class),
      $this->transactionManager(),
      self::FRONTEND_URL,
    );

    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('The selected plan is not available for purchase.');

    $handler(new StartCheckoutCommand('org-42', 'free', 'month'));
  }

  private function priceCatalog(string $planKey, string $interval, string $priceId): BillingPriceCatalog
  {
    return new BillingPriceCatalog(
      [$planKey => [$interval => ['priceId' => $priceId, 'amount' => 1900]]],
      'eur',
    );
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
