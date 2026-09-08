<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Provider\Facility;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Application\UseCase\Query\GeocodeAddress\{GeocodeAddressQuery, GeocodeAddressResult};
use Facility\Presentation\Api\Dto\Output\Facility\GeocodeAddressOutput;
use Facility\Presentation\Api\Provider\Facility\GeocodeAddressProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, TooManyRequestsHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * Test GeocodeAddressProviderTest.
 *
 * The provider is deliberately catch-free (FG-035): the handler's domain
 * exceptions map through `api_platform.exception_to_status`, so this test
 * covers only what the provider itself owns — authentication, the per-user
 * rate limit, the address-presence check, and the Result → Output mapping.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GeocodeAddressProvider::class)]
final class GeocodeAddressProviderTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655449501';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655449502';

  #[Test]
  public function testProvideDispatchesTheQueryAndMapsTheResult(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GeocodeAddressQuery $query): bool => self::USER_ID === $query->userId
        && self::ORG_ID === $query->organizationId
        && 'Paris' === $query->address))
      ->willReturn(new GeocodeAddressResult(48.8566, 2.3522, 'Paris, France'));

    $provider = new GeocodeAddressProvider(
      $queryBus,
      $this->securityWithUser(),
      $this->requestStackWithAddress('Paris'),
      $this->createRateLimiterFactory(limit: 30),
    );

    $output = $provider->provide(new Get(), ['organizationId' => self::ORG_ID]);

    self::assertInstanceOf(GeocodeAddressOutput::class, $output);
    self::assertSame(48.8566, $output->latitude);
    self::assertSame(2.3522, $output->longitude);
    self::assertSame('Paris, France', $output->displayName);
  }

  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new GeocodeAddressProvider(
      $this->createStub(QueryBusPort::class),
      $security,
      $this->requestStackWithAddress('Paris'),
      $this->createRateLimiterFactory(limit: 30),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['organizationId' => self::ORG_ID]);
  }

  #[Test]
  public function testProvideThrowsBadRequestWhenTheAddressParameterIsMissing(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GeocodeAddressProvider(
      $queryBus,
      $this->securityWithUser(),
      $this->requestStackWithAddress(null),
      $this->createRateLimiterFactory(limit: 30),
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new Get(), ['organizationId' => self::ORG_ID]);
  }

  #[Test]
  public function testProvideThrowsTooManyRequestsWhenThePerUserBudgetIsExhausted(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $rateLimiter = $this->createRateLimiterFactory(limit: 1);

    $provider = new GeocodeAddressProvider(
      $queryBus,
      $this->securityWithUser(),
      $this->requestStackWithAddress('Paris'),
      $rateLimiter,
    );

    // First call consumes the single token; asking again must 429 BEFORE
    // validation or dispatch — the limiter protects the outbound budget even
    // for requests that would fail later.
    $queryBusFirst = $this->createStub(QueryBusPort::class);
    $queryBusFirst->method('ask')->willReturn(new GeocodeAddressResult(0.0, 0.0, 'x'));
    new GeocodeAddressProvider(
      $queryBusFirst,
      $this->securityWithUser(),
      $this->requestStackWithAddress('Paris'),
      $rateLimiter,
    )->provide(new Get(), ['organizationId' => self::ORG_ID]);

    $this->expectException(TooManyRequestsHttpException::class);

    $provider->provide(new Get(), ['organizationId' => self::ORG_ID]);
  }

  private function createRateLimiterFactory(int $limit): RateLimiterFactory
  {
    return new RateLimiterFactory(
      config: [
        'id' => 'facility_geocode',
        'policy' => 'fixed_window',
        'limit' => $limit,
        'interval' => '1 hour',
      ],
      storage: new InMemoryStorage(),
    );
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

  private function requestStackWithAddress(?string $address): RequestStack
  {
    $requestStack = new RequestStack();
    $requestStack->push(new Request(null === $address ? [] : ['address' => $address]));

    return $requestStack;
  }
}
