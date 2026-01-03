<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\Oidc\Adapter;

use OAuth\Domain\Model\Oidc\OidcUser;
use OAuth\Infrastructure\Oidc\Adapter\OidcUserProviderAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Domain\ValueObject\Email;
use Tests\Helper\TestEventIdProvider;
use User\Application\UseCase\Query\GetUser\GetUserQuery;
use User\Application\UseCase\Query\GetUser\GetUserResult;
use User\Domain\Model\User;
use User\Domain\ValueObject\HashedPassword;
use User\Domain\ValueObject\UserId;
use User\Domain\ValueObject\Username;
use User\Domain\ValueObject\UserProfile;

use function password_hash;

use const PASSWORD_BCRYPT;

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

    $oidcUser = $adapter->findByIdentifier($user->id()->value);

    self::assertInstanceOf(OidcUser::class, $oidcUser);
    self::assertSame($user->id()->value, $oidcUser->subject());
    self::assertSame((string) $user->username(), $oidcUser->preferredUsername());
    self::assertSame((string) $user->email(), $oidcUser->email());
    self::assertTrue($oidcUser->emailVerified());
    self::assertSame($user->profile()->firstName, $oidcUser->givenName());
    self::assertSame($user->profile()->lastName, $oidcUser->familyName());
    self::assertSame($user->profile()->avatarUrl, $oidcUser->pictureUrl());
    self::assertNull($oidcUser->authTime());
  }

  private function createUser(): User
  {
    $eventIdProvider = new TestEventIdProvider();

    $user = User::register(
      id: new UserId('550e8400-e29b-41d4-a716-446655440000'),
      username: new Username('testuser'),
      email: new Email('test@example.com'),
      password: new HashedPassword(password_hash('password', PASSWORD_BCRYPT)),
      profile: new UserProfile('Test', 'User', 'https://cdn.example.com/avatar.png'),
      eventIdProvider: $eventIdProvider,
    );

    $user->verifyEmail(new TestEventIdProvider());

    return $user;
  }
  // #endregion
}
