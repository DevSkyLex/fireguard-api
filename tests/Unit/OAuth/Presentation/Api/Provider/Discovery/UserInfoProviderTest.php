<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Provider\Discovery;

use ApiPlatform\Metadata\Operation;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use OAuth\Application\Port\Outbound\User\OidcUserProviderPort;
use OAuth\Application\Service\OidcClaimsBuilderInterface;
use OAuth\Domain\Model\Oidc\OidcUser;
use OAuth\Presentation\Api\Dto\Output\Discovery\UserInfoOutput;
use OAuth\Presentation\Api\Provider\Discovery\UserInfoProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{HttpException, UnauthorizedHttpException};

/**
 * Test UserInfoProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: UserInfoProvider::class)]
final class UserInfoProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProvideThrowsWhenUserMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn(null);

    $provider = new UserInfoProvider(
      security: $security,
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
      claimsBuilder: $this->createStub(OidcClaimsBuilderInterface::class),
    );

    $this->expectException(UnauthorizedHttpException::class);

    $provider->provide(operation: $this->createStub(Operation::class));
  }

  #[Test]
  public function testProvideThrowsWhenOpenIdScopeMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser(scopes: ['email']));

    $provider = new UserInfoProvider(
      security: $security,
      oidcUserProvider: $this->createStub(OidcUserProviderPort::class),
      claimsBuilder: $this->createStub(OidcClaimsBuilderInterface::class),
    );

    $this->expectException(HttpException::class);

    $provider->provide(operation: $this->createStub(Operation::class));
  }

  #[Test]
  public function testProvideThrowsWhenOidcUserMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser(scopes: ['openid']));

    /** @var OidcUserProviderPort&MockObject $oidcUserProvider */
    $oidcUserProvider = $this->createMock(OidcUserProviderPort::class);
    $oidcUserProvider->expects(self::once())
      ->method('findByIdentifier')
      ->with('user-123')
      ->willReturn(null);

    $provider = new UserInfoProvider(
      security: $security,
      oidcUserProvider: $oidcUserProvider,
      claimsBuilder: $this->createStub(OidcClaimsBuilderInterface::class),
    );

    $this->expectException(UnauthorizedHttpException::class);

    $provider->provide(operation: $this->createStub(Operation::class));
  }

  #[Test]
  public function testProvideReturnsUserInfo(): void
  {
    $securityUser = $this->createSecurityUser(scopes: ['openid', 'profile']);

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($securityUser);

    $oidcUser = new OidcUser(
      subject: 'user-123',
      preferredUsername: 'user',
      email: 'user@example.com',
      emailVerified: true,
      givenName: 'Test',
      familyName: 'User',
      pictureUrl: 'https://cdn.example.com/avatar.png',
      authTime: new DateTimeImmutable('@1700000000'),
    );

    /** @var OidcUserProviderPort&MockObject $oidcUserProvider */
    $oidcUserProvider = $this->createMock(OidcUserProviderPort::class);
    $oidcUserProvider->expects(self::once())
      ->method('findByIdentifier')
      ->with('user-123')
      ->willReturn($oidcUser);

    /** @var OidcClaimsBuilderInterface&MockObject $claimsBuilder */
    $claimsBuilder = $this->createMock(OidcClaimsBuilderInterface::class);
    $claimsBuilder->expects(self::once())
      ->method('buildUserInfoClaims')
      ->with($oidcUser, ['openid', 'profile'])
      ->willReturn([
        'sub' => 'user-123',
        'name' => 'Test User',
        'given_name' => 'Test',
        'family_name' => 'User',
        'preferred_username' => 'user',
        'picture' => 'https://cdn.example.com/avatar.png',
        'email' => 'user@example.com',
        'email_verified' => true,
        'updated_at' => 1700000000,
      ]);

    $provider = new UserInfoProvider(
      security: $security,
      oidcUserProvider: $oidcUserProvider,
      claimsBuilder: $claimsBuilder,
    );

    $output = $provider->provide(operation: $this->createStub(Operation::class));

    self::assertInstanceOf(UserInfoOutput::class, $output);
    self::assertSame('user-123', $output->sub);
    self::assertSame('Test User', $output->name);
    self::assertSame('user@example.com', $output->email);
    self::assertTrue($output->emailVerified ?? false);
  }

  #[Test]
  public function testProvideHandlesInvalidClaimsAndFallbacks(): void
  {
    $securityUser = $this->createSecurityUser(scopes: ['openid']);

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($securityUser);

    $oidcUser = new OidcUser(
      subject: 'user-123',
      preferredUsername: 'user',
      email: 'user@example.com',
      emailVerified: false,
      givenName: null,
      familyName: null,
      pictureUrl: null,
      authTime: new DateTimeImmutable('@1700000000'),
    );

    /** @var OidcUserProviderPort&MockObject $oidcUserProvider */
    $oidcUserProvider = $this->createMock(OidcUserProviderPort::class);
    $oidcUserProvider->expects(self::once())
      ->method('findByIdentifier')
      ->willReturn($oidcUser);

    /** @var OidcClaimsBuilderInterface&MockObject $claimsBuilder */
    $claimsBuilder = $this->createMock(OidcClaimsBuilderInterface::class);
    $claimsBuilder->expects(self::once())
      ->method('buildUserInfoClaims')
      ->willReturn([
        'sub' => ' ',
        'name' => 123,
        'given_name' => '',
        'family_name' => null,
        'email_verified' => 'yes',
        'updated_at' => 'not-int',
      ]);

    $provider = new UserInfoProvider(
      security: $security,
      oidcUserProvider: $oidcUserProvider,
      claimsBuilder: $claimsBuilder,
    );

    $output = $provider->provide(operation: $this->createStub(Operation::class));

    self::assertSame('user-123', $output->sub);
    self::assertNull($output->name);
    self::assertNull($output->givenName);
    self::assertNull($output->familyName);
    self::assertNull($output->emailVerified);
    self::assertNull($output->updatedAt);
  }

  #[Test]
  public function testProvideWrapsUnexpectedException(): void
  {
    $securityUser = $this->createSecurityUser(scopes: ['openid']);

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($securityUser);

    /** @var OidcUserProviderPort&MockObject $oidcUserProvider */
    $oidcUserProvider = $this->createMock(OidcUserProviderPort::class);
    $oidcUserProvider->expects(self::once())
      ->method('findByIdentifier')
      ->willReturn(new OidcUser(
        subject: 'user-123',
        preferredUsername: 'user',
        email: 'user@example.com',
        emailVerified: false,
        givenName: null,
        familyName: null,
        pictureUrl: null,
        authTime: new DateTimeImmutable('@1700000000'),
      ));

    /** @var OidcClaimsBuilderInterface&MockObject $claimsBuilder */
    $claimsBuilder = $this->createMock(OidcClaimsBuilderInterface::class);
    $claimsBuilder->expects(self::once())
      ->method('buildUserInfoClaims')
      ->willThrowException(new RuntimeException('boom'));

    $provider = new UserInfoProvider(
      security: $security,
      oidcUserProvider: $oidcUserProvider,
      claimsBuilder: $claimsBuilder,
    );

    $this->expectException(UnauthorizedHttpException::class);
    $this->expectExceptionMessage('Failed to get user info: boom');

    $provider->provide(operation: $this->createStub(Operation::class));
  }

  /**
   * @param list<string> $scopes
   */
  private function createSecurityUser(array $scopes): SecurityUser
  {
    return new SecurityUser(
      id: 'user-123',
      email: 'user@example.com',
      password: 'hashed',
      roles: ['ROLE_USER'],
      scopes: $scopes,
      isActive: true,
    );
  }
  // #endregion
}
