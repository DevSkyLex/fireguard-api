<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Provider\Facility;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Application\Contract\Geocoding\AddressSuggestion;
use Facility\Application\UseCase\Query\SuggestAddresses\{SuggestAddressesQuery, SuggestAddressesResult};
use Facility\Presentation\Api\Dto\Output\Facility\SuggestAddressesOutput;
use Facility\Presentation\Api\Provider\Facility\SuggestAddressesProvider;
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
 * Test SuggestAddressesProviderTest.
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
#[CoversClass(SuggestAddressesProvider::class)]
final class SuggestAddressesProviderTest extends TestCase
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
      ->with(self::callback(static fn (SuggestAddressesQuery $query): bool => self::USER_ID === $query->userId
        && self::ORG_ID === $query->organizationId
        && 'Paris' === $query->query))
      ->willReturn(new SuggestAddressesResult([new AddressSuggestion('1 Rue de Paris, Paris, France', 48.8566, 2.3522, '1 Rue de Paris', 'Paris', 'Ile-de-France', '75001', 'France', 'fr')]));

    $provider = new SuggestAddressesProvider(
      $queryBus,
      $this->securityWithUser(),
      $this->requestStackWithAddress('Paris'),
      $this->createRateLimiterFactory(limit: 30),
    );

    $output = $provider->provide(new Get(), ['organizationId' => self::ORG_ID]);

    self::assertInstanceOf(SuggestAddressesOutput::class, $output);
    self::assertSame(48.8566, $output->member[0]->latitude);
    self::assertSame(2.3522, $output->member[0]->longitude);
    self::assertSame('1 Rue de Paris, Paris, France', $output->member[0]->displayName);
    self::assertSame('1 Rue de Paris', $output->member[0]->street);
    self::assertSame('Paris', $output->member[0]->city);
    self::assertSame('Ile-de-France', $output->member[0]->region);
    self::assertSame('75001', $output->member[0]->postalCode);
    self::assertSame('France', $output->member[0]->country);
    self::assertSame('fr', $output->member[0]->countryCode);
    self::assertSame(1, $output->totalItems);
  }

  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new SuggestAddressesProvider(
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

    $provider = new SuggestAddressesProvider(
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

    $provider = new SuggestAddressesProvider(
      $queryBus,
      $this->securityWithUser(),
      $this->requestStackWithAddress('Paris'),
      $rateLimiter,
    );

    // First call consumes the single token; asking again must 429 BEFORE
    // validation or dispatch — the limiter protects the outbound budget even
    // for requests that would fail later.
    $queryBusFirst = $this->createStub(QueryBusPort::class);
    $queryBusFirst->method('ask')->willReturn(new SuggestAddressesResult([]));
    new SuggestAddressesProvider(
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
        'id' => 'facility_address_suggestions',
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
    $requestStack->push(new Request(null === $address ? [] : ['q' => $address]));

    return $requestStack;
  }
}
