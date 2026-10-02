<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use Billing\Application\Port\Outbound\{OrganizationAccessPort, StripeGatewayPort, SubscriptionRepositoryPort};
use Billing\Domain\Exception\BillingGatewayUnavailableException;
use Billing\Domain\Model\Subscription\Subscription;
use Billing\Domain\ValueObject\SubscriptionId;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BillingApiTest extends WebTestCase
{
  private const string DUMMY_UUID = '550e8400-e29b-41d4-a716-446655440000';

  #[Test]
  public function testGatewayFailureUsesTheCentralHttp503Mapper(): void
  {
    $client = static::createClient();
    $client->disableReboot();
    $access = $this->createStub(OrganizationAccessPort::class);
    $access->method('hasPermission')->willReturn(true);
    $subscriptions = $this->createStub(SubscriptionRepositoryPort::class);
    $subscriptions->method('findByOrganizationId')->willReturn(Subscription::start(
      SubscriptionId::fromString('550e8400-e29b-41d4-a716-446655440001'),
      self::DUMMY_UUID,
      'cus_gateway_outage',
    ));
    $gateway = $this->createMock(StripeGatewayPort::class);
    $gateway->expects(self::once())->method('getPaymentMethod')->with('cus_gateway_outage')
      ->willThrowException(BillingGatewayUnavailableException::create());
    static::getContainer()->set(OrganizationAccessPort::class, $access);
    static::getContainer()->set(SubscriptionRepositoryPort::class, $subscriptions);
    static::getContainer()->set(StripeGatewayPort::class, $gateway);
    $client->loginUser(new SecurityUser(
      id: '550e8400-e29b-41d4-a716-446655440002',
      email: 'billing-outage@example.com',
      password: 'test-only-password',
      roles: ['ROLE_USER'],
    ), 'api');

    $client->request(
      'GET',
      '/api/organizations/' . self::DUMMY_UUID . '/billing/payment-method',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    self::assertResponseStatusCodeSame(503);
  }

  #[Test]
  public function testGetOrganizationPaymentMethodRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/billing/payment-method');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/{organizationId}/billing/payment-method endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations/{organizationId}/billing/payment-method, got ' . $statusCode,
    );
  }

  #[Test]
  public function testGetOrganizationSubscriptionRequiresAuthentication(): void
  {
    $client = static::createClient();

    $client->request('GET', '/api/organizations/' . self::DUMMY_UUID . '/billing/subscription');

    $statusCode = $client->getResponse()->getStatusCode();

    self::assertNotEquals(
      expected: 404,
      actual: $statusCode,
      message: 'GET /organizations/{organizationId}/billing/subscription endpoint should exist (got 404)',
    );

    self::assertContains(
      needle: $statusCode,
      haystack: [401, 403],
      message: 'Expected 401 or 403 for unauthenticated GET /organizations/{organizationId}/billing/subscription, got ' . $statusCode,
    );
  }
}
