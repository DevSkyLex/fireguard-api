<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\UseCase\Command\User\UpdateUser;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Email;
use Tests\Helper\TestEventIdProvider;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Application\UseCase\Command\User\UpdateUser\{UpdateUserCommand, UpdateUserHandler, UpdateUserResult};
use User\Domain\Exception\UserNotFoundException;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, Locale, UserId, UserProfile, Username};

/**
 * Test UpdateUserHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdateUserHandler::class)]
final class UpdateUserHandlerTest extends TestCase
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

    $handler = new UpdateUserHandler($repository);

    $this->expectException(UserNotFoundException::class);

    $handler->__invoke(new UpdateUserCommand(
      id: '550e8400-e29b-41d4-a716-446655440010',
      firstName: 'Jane',
      lastName: 'Doe',
      avatarUrl: null,
    ));
  }

  #[Test]
  public function testInvokeUpdatesProfile(): void
  {
    $user = User::register(
      id: new UserId('550e8400-e29b-41d4-a716-446655440011'),
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
      ->method('save')
      ->with($user);

    $handler = new UpdateUserHandler($repository);

    $result = $handler->__invoke(new UpdateUserCommand(
      id: $user->id()->value,
      firstName: 'Jane',
      lastName: null,
      avatarUrl: 'https://example.com/avatar.png',
    ));

    self::assertInstanceOf(UpdateUserResult::class, $result);
    self::assertSame('Jane', $user->profile()->firstName);
    self::assertSame('Doe', $user->profile()->lastName);
    self::assertSame('https://example.com/avatar.png', $user->profile()->avatarUrl);
  }

  #[Test]
  public function testInvokeUpdatesLocale(): void
  {
    $user = User::register(
      id: new UserId('550e8400-e29b-41d4-a716-446655440012'),
      username: new Username('jdoe'),
      email: new Email('jdoe@example.com'),
      password: HashedPassword::fromPlain('Password123!'),
      profile: new UserProfile('John', 'Doe', null),
      eventIdProvider: new TestEventIdProvider(),
    );

    /** @var UserRepositoryPort&MockObject $repository */
    $repository = $this->createMock(UserRepositoryPort::class);
    $repository->method('findById')->willReturn($user);
    $repository->expects(self::once())->method('save')->with($user);

    $handler = new UpdateUserHandler($repository);

    $handler->__invoke(new UpdateUserCommand(id: $user->id()->value, locale: 'fr'));

    self::assertSame(Locale::FR, $user->locale());
  }

  #[Test]
  public function testInvokeLeavesLocaleUnchangedWhenAbsent(): void
  {
    $user = User::register(
      id: new UserId('550e8400-e29b-41d4-a716-446655440013'),
      username: new Username('jdoe'),
      email: new Email('jdoe@example.com'),
      password: HashedPassword::fromPlain('Password123!'),
      profile: new UserProfile('John', 'Doe', null),
      eventIdProvider: new TestEventIdProvider(),
    );
    $user->updateLocale(Locale::ES);

    /** @var UserRepositoryPort&MockObject $repository */
    $repository = $this->createMock(UserRepositoryPort::class);
    $repository->method('findById')->willReturn($user);
    $repository->expects(self::once())->method('save')->with($user);

    $handler = new UpdateUserHandler($repository);

    // Name-only update: locale must not be clobbered.
    $handler->__invoke(new UpdateUserCommand(id: $user->id()->value, firstName: 'Jane'));

    self::assertSame(Locale::ES, $user->locale());
  }
  // #endregion
}
