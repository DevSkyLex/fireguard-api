<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\UseCase\Command\User\DeleteUser;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Email;
use Tests\Helper\TestEventIdProvider;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Application\UseCase\Command\User\DeleteUser\{DeleteUserCommand, DeleteUserHandler, DeleteUserResult};
use User\Domain\Exception\UserNotFoundException;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, Username};

/**
 * Test DeleteUserHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeleteUserHandler::class)]
final class DeleteUserHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeThrowsWhenMissing(): void
  {
    /** @var UserRepositoryPort&MockObject $repository */
    $repository = $this->createMock(UserRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $handler = new DeleteUserHandler($repository);

    $this->expectException(UserNotFoundException::class);

    $handler->__invoke(new DeleteUserCommand(id: '550e8400-e29b-41d4-a716-446655440000'));
  }

  #[Test]
  public function testInvokeDeletesUser(): void
  {
    $user = User::register(
      id: new UserId('550e8400-e29b-41d4-a716-446655440001'),
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
      ->willReturn($user);
    $repository->expects(self::once())
      ->method('delete')
      ->with($user);

    $handler = new DeleteUserHandler($repository);

    $result = $handler->__invoke(new DeleteUserCommand(id: $user->id()->value));

    self::assertInstanceOf(DeleteUserResult::class, $result);
    self::assertSame($user->id()->value, $result->userId);
  }
  // #endregion
}
