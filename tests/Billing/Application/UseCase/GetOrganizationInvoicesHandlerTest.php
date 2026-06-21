<?php

declare(strict_types=1);

namespace Tests\Billing\Application\UseCase;

use Billing\Application\Port\Outbound\Stripe\StripeInvoice;
use Billing\Application\Port\Outbound\{StripeGatewayPort, SubscriptionRepositoryPort};
use Billing\Application\UseCase\Query\GetOrganizationInvoices\{
  GetOrganizationInvoicesHandler,
  GetOrganizationInvoicesQuery
};
use Billing\Domain\Model\Subscription\Subscription;
use Billing\Domain\ValueObject\SubscriptionId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test GetOrganizationInvoicesHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GetOrganizationInvoicesHandlerTest extends TestCase
{
  private const string SUBSCRIPTION_ID = '33333333-3333-4333-8333-333333333333';

  #[Test]
  public function itReturnsNoInvoicesWhenTheOrganizationHasNoCustomer(): void
  {
    $stripe = $this->createMock(StripeGatewayPort::class);
    $stripe->expects(self::never())->method('listInvoices');

    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn(null);

    $handler = new GetOrganizationInvoicesHandler($subscriptions, $stripe);

    $result = $handler(new GetOrganizationInvoicesQuery('org-1'));

    self::assertSame([], $result->invoices);
  }

  #[Test]
  public function itListsInvoicesForTheOrganizationCustomer(): void
  {
    $invoice = new StripeInvoice(
      id: 'in_1',
      number: 'F-001',
      status: 'paid',
      amountPaid: 2900,
      amountDue: 2900,
      currency: 'EUR',
      createdAt: null,
      hostedInvoiceUrl: 'https://stripe.test/i/in_1',
      invoicePdf: 'https://stripe.test/i/in_1.pdf',
    );

    $subscription = Subscription::start(
      SubscriptionId::fromString(self::SUBSCRIPTION_ID),
      'org-1',
      'cus_1',
    );

    $stripe = $this->createMock(StripeGatewayPort::class);
    $stripe->expects(self::once())
      ->method('listInvoices')
      ->with('cus_1')
      ->willReturn([$invoice]);

    $subscriptions = $this->createMock(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn($subscription);

    $handler = new GetOrganizationInvoicesHandler($subscriptions, $stripe);

    $result = $handler(new GetOrganizationInvoicesQuery('org-1'));

    self::assertSame([$invoice], $result->invoices);
  }
}
