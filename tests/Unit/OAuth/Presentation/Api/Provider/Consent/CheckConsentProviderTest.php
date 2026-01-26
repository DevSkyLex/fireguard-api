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
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, UnauthorizedHttpException};

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
  // #endregion
}
