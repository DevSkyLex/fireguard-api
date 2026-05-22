<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Provider\Consent;

use ApiPlatform\Metadata\Operation;
use Auth\Infrastructure\Security\User\SecurityUser;
use OAuth\Application\UseCase\Query\Consent\CheckConsent\{CheckConsentQuery, CheckConsentResult};
use OAuth\Presentation\Api\Dto\Output\Consent\CheckConsentOutput;
use OAuth\Presentation\Api\Provider\Consent\CheckConsentProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, TooManyRequestsHttpException, UnauthorizedHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

use function hash;
use function sprintf;
use function substr;

/**
 * Test CheckConsentProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: CheckConsentProvider::class)]
final class CheckConsentProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProvideThrowsWhenUserMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn(null);

    $provider = new CheckConsentProvider(
      security: $security,
      queryBus: $this->createMock(QueryBusPort::class),
      requestStack: new RequestStack(),
    );

    $this->expectException(UnauthorizedHttpException::class);

    $provider->provide(operation: $this->createMock(Operation::class));
  }

  #[Test]
  public function testProvideThrowsWhenClientIdMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser());

    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/oauth2/consent', 'GET'));

    $provider = new CheckConsentProvider(
      security: $security,
      queryBus: $this->createMock(QueryBusPort::class),
      requestStack: $requestStack,
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(operation: $this->createMock(Operation::class));
  }

  #[Test]
  public function testProvideThrowsWhenRequestMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser());

    $provider = new CheckConsentProvider(
      security: $security,
      queryBus: $this->createMock(QueryBusPort::class),
      requestStack: new RequestStack(),
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(operation: $this->createMock(Operation::class));
  }

  #[Test]
  public function testProvideReturnsOutput(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser());

    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/oauth2/consent',
      method: 'GET',
      parameters: [
        'client_id' => 'client-123',
        'scope' => 'openid profile',
      ],
    ));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(CheckConsentQuery::class))
      ->willReturn(new CheckConsentResult(
        hasConsent: true,
        grantedScopes: ['openid'],
        missingScopes: ['profile'],
        requiresConsentScreen: true,
      ));

    $provider = new CheckConsentProvider(
      security: $security,
      queryBus: $queryBus,
      requestStack: $requestStack,
    );

    $output = $provider->provide(operation: $this->createMock(Operation::class));

    self::assertInstanceOf(CheckConsentOutput::class, $output);
    self::assertTrue($output->hasConsent);
    self::assertSame(['openid'], $output->grantedScopes);
    self::assertSame(['profile'], $output->missingScopes);
    self::assertTrue($output->requiresConsentScreen);
  }

  #[Test]
  public function testProvideHandlesEmptyScope(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser());

    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/oauth2/consent',
      method: 'GET',
      parameters: [
        'client_id' => 'client-123',
      ],
    ));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(
        static fn (CheckConsentQuery $query): bool => [] === $query->requestedScopes,
      ))
      ->willReturn(new CheckConsentResult(
        hasConsent: false,
        grantedScopes: [],
        missingScopes: [],
        requiresConsentScreen: false,
      ));

    $provider = new CheckConsentProvider(
      security: $security,
      queryBus: $queryBus,
      requestStack: $requestStack,
    );

    $output = $provider->provide(operation: $this->createMock(Operation::class));

    self::assertFalse($output->hasConsent);
  }

  #[Test]
  public function testProvideThrowsTooManyRequestsWhenRateLimited(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser());

    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/oauth2/consent',
      method: 'GET',
      parameters: [
        'client_id' => 'client-123',
      ],
    ));

    $rateLimiter = $this->createRateLimiterFactory(limit: 1);
    $rateLimiter->create($this->createRateLimitKey('user-123', 'client-123'))->consume();

    $provider = new CheckConsentProvider(
      security: $security,
      queryBus: $this->createMock(QueryBusPort::class),
      requestStack: $requestStack,
      rateLimiter: $rateLimiter,
    );

    $this->expectException(TooManyRequestsHttpException::class);

    $provider->provide(operation: $this->createMock(Operation::class));
  }

  private function createSecurityUser(): SecurityUser
  {
    return new SecurityUser(
      id: 'user-123',
      email: 'user@example.com',
      password: 'hashed',
      roles: ['ROLE_USER'],
      scopes: ['openid'],
      isActive: true,
    );
  }

  private function createRateLimiterFactory(int $limit = 10): RateLimiterFactory
  {
    return new RateLimiterFactory(
      config: [
        'id' => 'oauth_consent_check',
        'policy' => 'fixed_window',
        'limit' => $limit,
        'interval' => '1 hour',
      ],
      storage: new InMemoryStorage(),
    );
  }

  private function createRateLimitKey(string $userId, string $clientId): string
  {
    return sprintf(
      'oauth_consent_check_%s_%s',
      substr(hash('sha256', $userId), 0, 16),
      substr(hash('sha256', $clientId), 0, 16),
    );
  }
  // #endregion
}
