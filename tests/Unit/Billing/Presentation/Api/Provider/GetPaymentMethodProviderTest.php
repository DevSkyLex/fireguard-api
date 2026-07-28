<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Presentation\Api\Provider;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Billing\Application\Contract\Stripe\StripePaymentMethod;
use Billing\Application\Port\Outbound\OrganizationAccessPort;
use Billing\Application\UseCase\Query\GetOrganizationPaymentMethod\{
  GetOrganizationPaymentMethodQuery,
  GetOrganizationPaymentMethodResult
};
use Billing\Domain\Exception\BillingGatewayUnavailableException;
use Billing\Presentation\Api\Dto\Output\PaymentMethodOutput;
use Billing\Presentation\Api\Provider\GetPaymentMethodProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, ServiceUnavailableHttpException};

/**
 * Test GetPaymentMethodProviderTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetPaymentMethodProvider::class)]
final class GetPaymentMethodProviderTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441600';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655441610';

  #[Test]
  public function itThrowsWhenTheUserIsNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetPaymentMethodProvider(
      queryBus: $queryBus,
      access: $this->createStub(OrganizationAccessPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function itReturnsNullWhenTheOrganizationIdIsMissing(): void
  {
    $security = $this->securityWithUser();

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetPaymentMethodProvider(
      queryBus: $queryBus,
      access: $this->createStub(OrganizationAccessPort::class),
      security: $security,
    );

    self::assertNull($provider->provide(new Get(), []));
  }

  #[Test]
  public function itThrowsWhenThePermissionIsMissing(): void
  {
    $security = $this->securityWithUser();

    /** @var OrganizationAccessPort&MockObject $access */
    $access = $this->createMock(OrganizationAccessPort::class);
    $access->expects(self::once())
      ->method('hasPermission')
      ->with(self::USER_ID, self::ORGANIZATION_ID, 'organization.read')
      ->willReturn(false);

    $provider = new GetPaymentMethodProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      access: $access,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function itMapsThePaymentMethodWhenOneIsSaved(): void
  {
    $security = $this->securityWithUser();

    $access = $this->createStub(OrganizationAccessPort::class);
    $access->method('hasPermission')->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetOrganizationPaymentMethodQuery::class))
      ->willReturn(new GetOrganizationPaymentMethodResult(
        paymentMethod: new StripePaymentMethod(
          id: 'pm_1',
          brand: 'visa',
          last4: '4242',
          expMonth: 12,
          expYear: 2030,
        ),
      ));

    $provider = new GetPaymentMethodProvider(
      queryBus: $queryBus,
      access: $access,
      security: $security,
    );

    $output = $provider->provide(new Get(), ['organizationId' => self::ORGANIZATION_ID]);

    self::assertInstanceOf(PaymentMethodOutput::class, $output);
    self::assertSame(self::ORGANIZATION_ID, $output->organizationId);
    self::assertTrue($output->hasPaymentMethod);
    self::assertSame('visa', $output->brand);
    self::assertSame('4242', $output->last4);
    self::assertSame(12, $output->expMonth);
    self::assertSame(2030, $output->expYear);
  }

  #[Test]
  public function itReturnsAnEmptyResultWhenThereIsNoSavedCard(): void
  {
    $security = $this->securityWithUser();

    $access = $this->createStub(OrganizationAccessPort::class);
    $access->method('hasPermission')->willReturn(true);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new GetOrganizationPaymentMethodResult(paymentMethod: null));

    $provider = new GetPaymentMethodProvider(
      queryBus: $queryBus,
      access: $access,
      security: $security,
    );

    $output = $provider->provide(new Get(), ['organizationId' => self::ORGANIZATION_ID]);

    self::assertInstanceOf(PaymentMethodOutput::class, $output);
    self::assertFalse($output->hasPaymentMethod);
    self::assertNull($output->brand);
    self::assertNull($output->last4);
    self::assertNull($output->expMonth);
    self::assertNull($output->expYear);
  }

  #[Test]
  public function itDegradesToServiceUnavailableWhenStripeCannotBeReached(): void
  {
    $security = $this->securityWithUser();

    $access = $this->createStub(OrganizationAccessPort::class);
    $access->method('hasPermission')->willReturn(true);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(
      MessengerRuntimeException::wrap(BillingGatewayUnavailableException::create()),
    );

    $provider = new GetPaymentMethodProvider(
      queryBus: $queryBus,
      access: $access,
      security: $security,
    );

    $this->expectException(ServiceUnavailableHttpException::class);

    $provider->provide(new Get(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function itRethrowsAMessengerFailureThatIsNotAGatewayOutage(): void
  {
    $security = $this->securityWithUser();

    $access = $this->createStub(OrganizationAccessPort::class);
    $access->method('hasPermission')->willReturn(true);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(
      MessengerRuntimeException::wrap(new RuntimeException('Database down.')),
    );

    $provider = new GetPaymentMethodProvider(
      queryBus: $queryBus,
      access: $access,
      security: $security,
    );

    $this->expectException(MessengerRuntimeException::class);

    $provider->provide(new Get(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  private function securityWithUser(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }
}
