<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Presentation\Api\Provider;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use Billing\Application\Contract\Stripe\StripeInvoice;
use Billing\Application\Port\Outbound\OrganizationAccessPort;
use Billing\Application\UseCase\Query\GetOrganizationInvoices\{GetOrganizationInvoicesQuery, GetOrganizationInvoicesResult};
use Billing\Presentation\Api\Provider\GetInvoicesProvider;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Test GetInvoicesProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetInvoicesProvider::class)]
final class GetInvoicesProviderTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64b01';

  private const string USER_ID = 'user-id';

  #[Test]
  public function testProvideMapsEveryInvoiceToItsOutput(): void
  {
    $captured = null;
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturnCallback(static function (GetOrganizationInvoicesQuery $query) use (&$captured): GetOrganizationInvoicesResult {
        $captured = $query;

        return new GetOrganizationInvoicesResult([
          new StripeInvoice(
            id: 'in_1',
            number: 'FG-0001',
            status: 'paid',
            amountPaid: 2900,
            amountDue: 2900,
            currency: 'eur',
            createdAt: new DateTimeImmutable('2026-07-18T00:00:00+00:00'),
            hostedInvoiceUrl: 'https://invoice.stripe.com/i/1',
            invoicePdf: 'https://invoice.stripe.com/i/1.pdf',
          ),
        ]);
      });

    $provider = new GetInvoicesProvider($queryBus, $this->access(true), $this->authenticatedSecurity());

    $outputs = $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);

    self::assertInstanceOf(GetOrganizationInvoicesQuery::class, $captured);

    self::assertCount(1, $outputs);
    self::assertSame('in_1', $outputs[0]->id);
    self::assertSame('FG-0001', $outputs[0]->number);
    self::assertSame('paid', $outputs[0]->status);
    self::assertSame(2900, $outputs[0]->amount);
    self::assertSame('eur', $outputs[0]->currency);
    self::assertSame('2026-07-18T00:00:00+00:00', $outputs[0]->createdAt);
    self::assertSame('https://invoice.stripe.com/i/1', $outputs[0]->hostedInvoiceUrl);
    self::assertSame('https://invoice.stripe.com/i/1.pdf', $outputs[0]->invoicePdf);
  }

  #[Test]
  public function testProvideFallsBackToTheAmountDueForAnUnpaidInvoice(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new GetOrganizationInvoicesResult([
      new StripeInvoice(
        id: 'in_2',
        number: null,
        status: 'open',
        amountPaid: 0,
        amountDue: 4900,
        currency: 'eur',
        createdAt: null,
        hostedInvoiceUrl: null,
        invoicePdf: null,
      ),
    ]));

    $provider = new GetInvoicesProvider($queryBus, $this->access(true), $this->authenticatedSecurity());

    $outputs = $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);

    self::assertSame(4900, $outputs[0]->amount);
    self::assertNull($outputs[0]->number);
    self::assertNull($outputs[0]->createdAt);
  }

  #[Test]
  public function testProvideRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new GetInvoicesProvider($this->createStub(QueryBusPort::class), $this->access(true), $security);

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProvideReturnsAnEmptyListWithoutAnOrganizationId(): void
  {
    $provider = new GetInvoicesProvider(
      $this->createStub(QueryBusPort::class),
      $this->access(true),
      $this->authenticatedSecurity(),
    );

    self::assertSame([], $provider->provide(new GetCollection(), []));
  }

  #[Test]
  public function testProvideRequiresTheOrganizationReadPermission(): void
  {
    $provider = new GetInvoicesProvider(
      $this->createStub(QueryBusPort::class),
      $this->access(false),
      $this->authenticatedSecurity(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  /**
   * Method access.
   *
   * @param bool $granted whether the permission check succeeds
   *
   * @return OrganizationAccessPort the access port stub
   */
  private function access(bool $granted): OrganizationAccessPort
  {
    $access = $this->createStub(OrganizationAccessPort::class);
    $access->method('hasPermission')->willReturn($granted);

    return $access;
  }

  /**
   * Method authenticatedSecurity.
   *
   * @return Security a security stub returning an authenticated user
   */
  private function authenticatedSecurity(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    return $security;
  }
}
