<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\UseCase\Query\AuthenticateUser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Email;
use Tests\Helper\TestEventIdProvider;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Application\UseCase\Query\AuthenticateUser\AuthenticateUserHandler;
use User\Application\UseCase\Query\AuthenticateUser\AuthenticateUserQuery;
use User\Application\UseCase\Query\AuthenticateUser\AuthenticateUserResult;
use User\Domain\Model\User;
use User\Domain\ValueObject\HashedPassword;
use User\Domain\ValueObject\UserId;
use User\Domain\ValueObject\Username;
use User\Domain\ValueObject\UserProfile;

/**
 * Test AuthenticateUserHandlerTest.
 *
 * @category Handler Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AuthenticateUserHandler::class)]
final class AuthenticateUserHandlerTest extends TestCase
{
  // #region Properties
  /**
   * Property userRepository.
   *
   * Mock of the user repository.
   */
  private UserRepositoryPort&MockObject $userRepository;

  /**
   * Property handler.
   *
   * The handler under test.
   */
  private AuthenticateUserHandler $handler;
  // #endregion

  // #region Setup
  /**
   * Method setUp.
   *
   * Set up the test environment.
   *
   * @return void no return value
   */
  protected function setUp(): void
  {
    $this->userRepository = $this->createMock(UserRepositoryPort::class);
    $this->handler = new AuthenticateUserHandler($this->userRepository);
  }
  // #endregion

  // #region Methods
  /**
   * Method testAuthenticatesValidCredentials.
   *
   * Tests that the handler authenticates
   * valid credentials successfully.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testAuthenticatesValidCredentials(): void
  {
    // Arrange
    $query = new AuthenticateUserQuery('jdoe', 'password123');

    $eventIdProvider = new TestEventIdProvider();
    $user = User::register(
      id: new UserId('550e8400-e29b-41d4-a716-446655440000'),
      username: new Username('jdoe'),
      email: new Email('jdoe@example.com'),
      password: HashedPassword::fromPlain('password123'),
      profile: new UserProfile('John', 'Doe'),
      eventIdProvider: $eventIdProvider,
    );
    $user->verifyEmail($eventIdProvider);

    $this->userRepository->method('findByUsername')->willReturn($user);
    $this->userRepository->expects($this->once())->method('save')->with($user);

    // Act
    $result = ($this->handler)($query);

    // Assert
    $this->assertInstanceOf(AuthenticateUserResult::class, $result);
    $this->assertTrue($result->authenticated);
    $this->assertEquals($user->id()->value, $result->userId);
  }

  /**
   * Method testFailsIfUserNotFound.
   *
   * Tests that the handler fails when
   * user is not found.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testFailsIfUserNotFound(): void
  {
    // Arrange
    $query = new AuthenticateUserQuery('jdoe', 'password123');
    $this->userRepository->method('findByUsername')->willReturn(null);

    // Act
    $result = ($this->handler)($query);

    // Assert
    $this->assertInstanceOf(AuthenticateUserResult::class, $result);
    $this->assertFalse($result->authenticated);
  }

  /**
   * Method testFailsIfPasswordInvalid.
   *
   * Tests that the handler fails when
   * password is invalid.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testFailsIfPasswordInvalid(): void
  {
    // Arrange
    $query = new AuthenticateUserQuery('jdoe', 'wrongpassword');

    $eventIdProvider = new TestEventIdProvider();
    $user = User::register(
      id: new UserId('550e8400-e29b-41d4-a716-446655440001'),
      username: new Username('jdoe'),
      email: new Email('jdoe@example.com'),
      password: HashedPassword::fromPlain('password123'),
      profile: new UserProfile('John', 'Doe'),
      eventIdProvider: $eventIdProvider,
    );
    $user->verifyEmail($eventIdProvider);

    $this->userRepository->method('findByUsername')->willReturn($user);

    // Act
    $result = ($this->handler)($query);

    // Assert
    $this->assertInstanceOf(AuthenticateUserResult::class, $result);
    $this->assertFalse($result->authenticated);
  }
  // #endregion
}
