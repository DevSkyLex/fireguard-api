<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\UseCase\Command\User;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventBusPort;
use Shared\Domain\ValueObject\Email;
use Tests\Helper\TestEventIdProvider;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Application\UseCase\Command\User\ActivateUser\{ActivateUserCommand, ActivateUserHandler};
use User\Application\UseCase\Command\User\DeactivateUser\{DeactivateUserCommand, DeactivateUserHandler};
use User\Application\UseCase\Command\User\VerifyUserEmail\{VerifyUserEmailCommand, VerifyUserEmailHandler};
use User\Domain\Exception\UserNotFoundException;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, Username};

/**
 * Test UserStatusHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ActivateUserHandler::class)]
#[CoversClass(DeactivateUserHandler::class)]
#[CoversClass(VerifyUserEmailHandler::class)]
final class UserStatusHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testActivateUserThrowsWhenMissing(): void
  {
    /** @var UserRepositoryPort&MockObject $repository */
    $repository = $this->createMock(UserRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $handler = new ActivateUserHandler($repository);

    $this->expectException(UserNotFoundException::class);

    $handler->__invoke(new ActivateUserCommand(id: '550e8400-e29b-41d4-a716-446655440099'));
  }

  #[Test]
  public function testActivateUserSavesUser(): void
  {
    $user = $this->createUser('550e8400-e29b-41d4-a716-446655440100');

    /** @var UserRepositoryPort&MockObject $repository */
    $repository = $this->createMock(UserRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($user);
    $repository->expects(self::once())
      ->method('save')
      ->with($user);

    $handler = new ActivateUserHandler($repository);
    $handler->__invoke(new ActivateUserCommand(id: $user->id()->value));

    self::assertSame($user->id()->value, $user->id()->value);
  }

  #[Test]
  public function testDeactivateUserThrowsWhenMissing(): void
  {
    /** @var UserRepositoryPort&MockObject $repository */
    $repository = $this->createMock(UserRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $handler = new DeactivateUserHandler($repository);

    $this->expectException(UserNotFoundException::class);

    $handler->__invoke(new DeactivateUserCommand(id: '550e8400-e29b-41d4-a716-446655440098'));
  }

  #[Test]
  public function testDeactivateUserSavesUser(): void
  {
    $user = $this->createUser('550e8400-e29b-41d4-a716-446655440101');

    /** @var UserRepositoryPort&MockObject $repository */
    $repository = $this->createMock(UserRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($user);
    $repository->expects(self::once())
      ->method('save')
      ->with($user);

    $handler = new DeactivateUserHandler($repository);
    $handler->__invoke(new DeactivateUserCommand(id: $user->id()->value));

    self::assertSame($user->id()->value, $user->id()->value);
  }

  #[Test]
  public function testVerifyUserEmailThrowsWhenMissing(): void
  {
    /** @var UserRepositoryPort&MockObject $repository */
    $repository = $this->createMock(UserRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $handler = new VerifyUserEmailHandler(
      userRepository: $repository,
      eventBus: $this->createStub(EventBusPort::class),
      eventIdProvider: new TestEventIdProvider(),
    );

    $this->expectException(UserNotFoundException::class);

    $handler->__invoke(new VerifyUserEmailCommand(id: '550e8400-e29b-41d4-a716-446655440097'));
  }

  #[Test]
  public function testVerifyUserEmailPublishesEvents(): void
  {
    $user = $this->createUser('550e8400-e29b-41d4-a716-446655440102');
    $user->releaseEvents();

    /** @var UserRepositoryPort&MockObject $repository */
    $repository = $this->createMock(UserRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($user);
    $repository->expects(self::once())
      ->method('save')
      ->with($user);

    /** @var EventBusPort&MockObject $eventBus */
    $eventBus = $this->createMock(EventBusPort::class);
    $eventBus->expects(self::atLeastOnce())
      ->method('publish');

    $handler = new VerifyUserEmailHandler(
      userRepository: $repository,
      eventBus: $eventBus,
      eventIdProvider: new TestEventIdProvider(),
    );

    $handler->__invoke(new VerifyUserEmailCommand(id: $user->id()->value));
  }

  private function createUser(string $id): User
  {
    return User::register(
      id: new UserId($id),
      username: new Username('jdoe'),
      email: new Email('jdoe@example.com'),
      password: HashedPassword::fromPlain('Password123!'),
      profile: new UserProfile('John', 'Doe', null),
      eventIdProvider: new TestEventIdProvider(),
    );
  }
  // #endregion
}
