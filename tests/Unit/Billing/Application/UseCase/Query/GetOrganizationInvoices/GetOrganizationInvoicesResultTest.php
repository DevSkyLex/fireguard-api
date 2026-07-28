<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Application\UseCase\Query\GetOrganizationInvoices;

use Billing\Application\Contract\Stripe\StripeInvoice;
use Billing\Application\UseCase\Query\GetOrganizationInvoices\GetOrganizationInvoicesResult;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\ResultMessage;

/**
 * Test GetOrganizationInvoicesResultTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetOrganizationInvoicesResult::class)]
final class GetOrganizationInvoicesResultTest extends TestCase
{
  #[Test]
  public function testItCarriesTheInvoiceListAndIsAResultMessage(): void
  {
    $invoice = new StripeInvoice(
      id: 'in_1',
      number: 'FG-0001',
      status: 'paid',
      amountPaid: 2900,
      amountDue: 2900,
      currency: 'eur',
      createdAt: new DateTimeImmutable('2026-07-18T00:00:00+00:00'),
      hostedInvoiceUrl: 'https://invoice.stripe.com/i/1',
      invoicePdf: 'https://invoice.stripe.com/i/1.pdf',
    );

    $result = new GetOrganizationInvoicesResult([$invoice]);

    self::assertInstanceOf(ResultMessage::class, $result);
    self::assertSame([$invoice], $result->invoices);
  }

  #[Test]
  public function testItSupportsAnEmptyInvoiceList(): void
  {
    self::assertSame([], new GetOrganizationInvoicesResult([])->invoices);
  }
}
