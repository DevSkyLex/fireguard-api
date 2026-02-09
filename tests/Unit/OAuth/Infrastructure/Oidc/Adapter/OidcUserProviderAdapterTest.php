<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\Oidc\Adapter;

use DateTimeImmutable;
use OAuth\Domain\Model\Oidc\OidcUser;
use OAuth\Infrastructure\Oidc\Adapter\OidcUserProviderAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};
use User\Domain\ValueObject\UserId;

/**
 * Test OidcUserProviderAdapterTest.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: OidcUserProviderAdapter::class)]
final class OidcUserProviderAdapterTest extends TestCase
{
  // #region Methods
  /**
   * Method testFindByIdentifierReturnsNullForEmptyIdentifier.
   *
   * @return void no return value
   */
  #[Test]
  public function testFindByIdentifierReturnsNullForEmptyIdentifier(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $adapter = new OidcUserProviderAdapter(queryBus: $queryBus);

    self::assertNull($adapter->findByIdentifier('  '));
  }

  /**
   * Method testFindByIdentifierReturnsNullOnQueryFailure.
   *
   * @return void no return value
   */
  #[Test]
  public function testFindByIdentifierReturnsNullOnQueryFailure(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetUserQuery::class))
      ->willThrowException(new RuntimeException('failure'));

    $adapter = new OidcUserProviderAdapter(queryBus: $queryBus);

    self::assertNull($adapter->findByIdentifier('user-id'));
  }

  #[Test]
  public function testFindByIdentifierReturnsNullWhenUserMissing(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetUserQuery::class))
      ->willReturn(new GetUserResult(null));

    $adapter = new OidcUserProviderAdapter(queryBus: $queryBus);

    self::assertNull($adapter->findByIdentifier('user-id'));
  }

  /**
   * Method testFindByIdentifierReturnsOidcUser.
   *
   * @return void no return value
   */
  #[Test]
  public function testFindByIdentifierReturnsOidcUser(): void
  {
    $user = $this->createUser();

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetUserQuery::class))
      ->willReturn(new GetUserResult($user));

    $adapter = new OidcUserProviderAdapter(queryBus: $queryBus);

    $oidcUser = $adapter->findByIdentifier($user->id);

    self::assertInstanceOf(OidcUser::class, $oidcUser);
    self::assertSame($user->id, $oidcUser->subject());
    self::assertSame($user->username, $oidcUser->preferredUsername());
    self::assertSame($user->email, $oidcUser->email());
    self::assertTrue($oidcUser->emailVerified());
    self::assertSame($user->firstName, $oidcUser->givenName());
    self::assertSame($user->lastName, $oidcUser->familyName());
    self::assertSame($user->avatarUrl, $oidcUser->pictureUrl());
    self::assertSame($user->lastLoginAt, $oidcUser->authTime());
  }

  private function createUser(): UserView
  {
    return new UserView(
      id: new UserId('550e8400-e29b-41d4-a716-446655440000')->value,
      username: 'testuser',
      email: 'test@example.com',
      firstName: 'Test',
      lastName: 'User',
      avatarUrl: 'https://cdn.example.com/avatar.png',
      status: 'active',
      emailVerified: true,
      tenantId: null,
      createdAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
      lastLoginAt: new DateTimeImmutable('2024-01-02T00:00:00+00:00'),
      canLogin: true,
    );
  }
  // #endregion
}
