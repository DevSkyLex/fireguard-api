<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\UseCase\Query\User\GetUser;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Email;
use Tests\Helper\TestEventIdProvider;
use User\Application\Contract\User\UserView;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Application\UseCase\Query\User\GetUser\{GetUserHandler, GetUserQuery, GetUserResult};
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, Username};

/**
 * Test GetUserHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetUserHandler::class)]
final class GetUserHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeReturnsNullWhenMissing(): void
  {
    /** @var UserRepositoryPort&MockObject $repository */
    $repository = $this->createMock(UserRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $handler = new GetUserHandler($repository);

    $result = $handler->__invoke(new GetUserQuery(id: '550e8400-e29b-41d4-a716-446655440020'));

    self::assertInstanceOf(GetUserResult::class, $result);
    self::assertNull($result->user);
  }

  #[Test]
  public function testInvokeMapsUserView(): void
  {
    $user = User::register(
      id: new UserId('550e8400-e29b-41d4-a716-446655440021'),
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

    $handler = new GetUserHandler($repository);

    $result = $handler->__invoke(new GetUserQuery(id: $user->id()->value));

    self::assertInstanceOf(GetUserResult::class, $result);
    self::assertInstanceOf(UserView::class, $result->user);
    self::assertSame($user->id()->value, $result->user->id);
    self::assertSame('jdoe', $result->user->username);
  }
  // #endregion
}
