<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\UseCase\Query\User\GetCurrentUserProfile;

use Authorization\Application\Port\Inbound\AuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Email;
use Tests\Helper\TestEventIdProvider;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Application\UseCase\Query\User\GetCurrentUserProfile\{
  GetCurrentUserProfileHandler,
  GetCurrentUserProfileQuery,
  GetCurrentUserProfileResult
};
use User\Domain\Exception\UserNotFoundException;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, Username};

#[CoversClass(GetCurrentUserProfileHandler::class)]
final class GetCurrentUserProfileHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeReturnsCurrentUserProfile(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655442001';
    $user = User::register(
      id: new UserId($userId),
      username: new Username('jdoe'),
      email: new Email('jdoe@example.com'),
      password: HashedPassword::fromPlain('Password123!'),
      profile: new UserProfile('John', 'Doe', null),
      eventIdProvider: new TestEventIdProvider(),
    );

    /** @var UserRepositoryPort&MockObject $repository */
    $repository = $this->createMock(UserRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->with(self::callback(static fn (UserId $id): bool => $userId === $id->value))
      ->willReturn($user);

    /** @var AuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(AuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('getUserRoleNames')
      ->with($userId)
      ->willReturn(['admin']);
    $authorization->expects(self::once())
      ->method('getUserPermissionNames')
      ->with($userId)
      ->willReturn(['users.read', 'roles.read']);

    $handler = new GetCurrentUserProfileHandler(
      userRepository: $repository,
      authorization: $authorization,
    );

    $result = $handler->__invoke(new GetCurrentUserProfileQuery($userId));

    self::assertInstanceOf(GetCurrentUserProfileResult::class, $result);
    self::assertSame($userId, $result->user->id);
    self::assertSame('jdoe', $result->user->username);
    self::assertSame(['admin'], $result->roles);
    self::assertSame(['users.read', 'roles.read'], $result->permissions);
  }

  #[Test]
  public function testInvokeThrowsWhenAuthenticatedUserCannotBeResolved(): void
  {
    /** @var UserRepositoryPort&MockObject $repository */
    $repository = $this->createMock(UserRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    /** @var AuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(AuthorizationPort::class);
    $authorization->expects(self::never())->method('getUserRoleNames');
    $authorization->expects(self::never())->method('getUserPermissionNames');

    $handler = new GetCurrentUserProfileHandler(
      userRepository: $repository,
      authorization: $authorization,
    );

    $this->expectException(UserNotFoundException::class);

    $handler->__invoke(new GetCurrentUserProfileQuery('550e8400-e29b-41d4-a716-446655442002'));
  }
}
