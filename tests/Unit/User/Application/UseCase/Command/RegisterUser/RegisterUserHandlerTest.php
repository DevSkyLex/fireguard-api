<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\UseCase\Command\RegisterUser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventBusPort;
use Shared\Domain\ValueObject\Email;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Application\UseCase\Command\RegisterUser\RegisterUserCommand;
use User\Application\UseCase\Command\RegisterUser\RegisterUserHandler;
use User\Application\UseCase\Command\RegisterUser\RegisterUserResult;
use User\Domain\Exception\UserAlreadyExistsException;
use User\Domain\Model\User;
use User\Domain\ValueObject\Username;

/**
 * Test RegisterUserHandlerTest
 * @final
 *
 * Unit tests for the RegisterUserHandler.
 *
 * @category Handler Tests
 * @package Tests\Unit\User\Application\UseCase\Command\RegisterUser
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RegisterUserHandler::class)]
final class RegisterUserHandlerTest extends TestCase
{
  //#region Properties
  /**
   * Property userRepository
   *
   * Mock of the user repository.
   *
   * @access private
   *
   * @var UserRepositoryPort&MockObject
   */
  private UserRepositoryPort&MockObject $userRepository;

  /**
   * Property eventBus
   *
   * Mock of the event bus.
   *
   * @access private
   *
   * @var EventBusPort&MockObject
   */
  private EventBusPort&MockObject $eventBus;

  /**
   * Property handler
   *
   * The handler under test.
   *
   * @access private
   *
   * @var RegisterUserHandler
   */
  private RegisterUserHandler $handler;
  //#endregion

  //#region Setup
  /**
   * Method setUp
   *
   * Set up the test environment.
   *
   * @access protected
   *
   * @return void No return value.
   */
  protected function setUp(): void
  {
    $this->userRepository = $this->createMock(UserRepositoryPort::class);
    $this->eventBus = $this->createMock(EventBusPort::class);
    $this->handler = new RegisterUserHandler($this->userRepository, $this->eventBus);
  }
  //#endregion

  //#region Methods
  /**
   * Method testRegistersANewUser
   *
   * Tests that the handler registers
   * a new user successfully.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  #[Test]
  public function testRegistersANewUser(): void
  {
    // Arrange
    $command = new RegisterUserCommand(
      username: 'jdoe',
      email: 'jdoe@example.com',
      password: 'password123',
      firstName: 'John',
      lastName: 'Doe'
    );

    $this->userRepository->expects($this->once())
      ->method('existsByUsername')
      ->with($this->callback(fn(Username $u) => $u->value === 'jdoe'))
      ->willReturn(false);

    $this->userRepository->expects($this->once())
      ->method('existsByEmail')
      ->with($this->callback(fn(Email $e) => $e->value === 'jdoe@example.com'))
      ->willReturn(false);

    $this->userRepository->expects($this->once())
      ->method('save')
      ->with($this->isInstanceOf(User::class));

    $this->eventBus->expects($this->once())
      ->method('publish');

    // Act
    $result = ($this->handler)($command);

    // Assert
    $this->assertInstanceOf(RegisterUserResult::class, $result);
    $this->assertNotEmpty($result->userId);
  }

  /**
   * Method testThrowsIfUsernameExists
   *
   * Tests that the handler throws an exception
   * when username already exists.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  #[Test]
  public function testThrowsIfUsernameExists(): void
  {
    // Arrange
    $command = new RegisterUserCommand(
      username: 'jdoe',
      email: 'jdoe@example.com',
      password: 'password123',
      firstName: 'John',
      lastName: 'Doe'
    );

    $this->userRepository->method('existsByUsername')->willReturn(true);

    // Assert
    $this->expectException(UserAlreadyExistsException::class);

    // Act
    ($this->handler)($command);
  }

  /**
   * Method testThrowsIfEmailExists
   *
   * Tests that the handler throws an exception
   * when email already exists.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  #[Test]
  public function testThrowsIfEmailExists(): void
  {
    // Arrange
    $command = new RegisterUserCommand(
      username: 'jdoe',
      email: 'jdoe@example.com',
      password: 'password123',
      firstName: 'John',
      lastName: 'Doe'
    );

    $this->userRepository->method('existsByUsername')->willReturn(false);
    $this->userRepository->method('existsByEmail')->willReturn(true);

    // Assert
    $this->expectException(UserAlreadyExistsException::class);

    // Act
    ($this->handler)($command);
  }
  //#endregion
}
