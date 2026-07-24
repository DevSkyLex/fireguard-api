<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Application\UseCase;

use Billing\Application\Contract\Stripe\StripePaymentMethod;
use Billing\Application\Port\Outbound\{StripeGatewayPort, SubscriptionRepositoryPort};
use Billing\Application\UseCase\Query\GetOrganizationPaymentMethod\{
  GetOrganizationPaymentMethodHandler,
  GetOrganizationPaymentMethodQuery
};
use Billing\Domain\Model\Subscription\Subscription;
use Billing\Domain\ValueObject\SubscriptionId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test GetOrganizationPaymentMethodHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GetOrganizationPaymentMethodHandlerTest extends TestCase
{
  private const string SUBSCRIPTION_ID = '44444444-4444-4444-8444-444444444444';

  #[Test]
  public function itReturnsNoPaymentMethodWhenTheOrganizationHasNoCustomer(): void
  {
    $stripe = $this->createMock(StripeGatewayPort::class);
    $stripe->expects(self::never())->method('getPaymentMethod');

    $subscriptions = $this->createStub(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn(null);

    $handler = new GetOrganizationPaymentMethodHandler($subscriptions, $stripe);

    $result = $handler(new GetOrganizationPaymentMethodQuery('org-1'));

    self::assertNull($result->paymentMethod);
  }

  #[Test]
  public function itReturnsNoPaymentMethodWhenTheCustomerHasNoSavedCard(): void
  {
    $subscription = Subscription::start(
      SubscriptionId::fromString(self::SUBSCRIPTION_ID),
      'org-1',
      'cus_1',
    );

    $stripe = $this->createMock(StripeGatewayPort::class);
    $stripe->expects(self::once())
      ->method('getPaymentMethod')
      ->with('cus_1')
      ->willReturn(null);

    $subscriptions = $this->createStub(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn($subscription);

    $handler = new GetOrganizationPaymentMethodHandler($subscriptions, $stripe);

    $result = $handler(new GetOrganizationPaymentMethodQuery('org-1'));

    self::assertNull($result->paymentMethod);
  }

  #[Test]
  public function itReturnsTheSavedPaymentMethodForTheOrganizationCustomer(): void
  {
    $paymentMethod = new StripePaymentMethod(
      id: 'pm_1',
      brand: 'visa',
      last4: '4242',
      expMonth: 12,
      expYear: 2030,
    );

    $subscription = Subscription::start(
      SubscriptionId::fromString(self::SUBSCRIPTION_ID),
      'org-1',
      'cus_1',
    );

    $stripe = $this->createMock(StripeGatewayPort::class);
    $stripe->expects(self::once())
      ->method('getPaymentMethod')
      ->with('cus_1')
      ->willReturn($paymentMethod);

    $subscriptions = $this->createStub(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn($subscription);

    $handler = new GetOrganizationPaymentMethodHandler($subscriptions, $stripe);

    $result = $handler(new GetOrganizationPaymentMethodQuery('org-1'));

    self::assertSame($paymentMethod, $result->paymentMethod);
  }
}
