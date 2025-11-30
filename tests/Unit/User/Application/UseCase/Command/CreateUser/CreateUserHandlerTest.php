<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\UseCase\Command\CreateUser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventBusPort;
use Shared\Application\Port\Outbound\HashingPort;
use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Shared\Domain\ValueObject\HashedSecret;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Application\UseCase\Command\CreateUser\CreateUserCommand;
use User\Application\UseCase\Command\CreateUser\CreateUserHandler;
use User\Application\UseCase\Command\CreateUser\CreateUserResult;
use User\Domain\Model\User;

/**
 * Test CreateUserHandlerTest
 * @final
 *
 * Unit tests for the CreateUserHandler.
 *
 * @category Handler Tests
 * @package Tests\Unit\User\Application\UseCase\Command\CreateUser
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateUserHandler::class)]
final class CreateUserHandlerTest extends TestCase
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
   * Property uuidGenerator
   *
   * Mock of the UUID generator.
   *
   * @access private
   *
   * @var UuidGeneratorPort&MockObject
   */
  private UuidGeneratorPort&MockObject $uuidGenerator;

  /**
   * Property hashing
   *
   * Mock of the hashing service.
   *
   * @access private
   *
   * @var HashingPort&MockObject
   */
  private HashingPort&MockObject $hashing;

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
   * @var CreateUserHandler
   */
  private CreateUserHandler $handler;
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
    $this->uuidGenerator = $this->createMock(UuidGeneratorPort::class);
    $this->hashing = $this->createMock(HashingPort::class);
    $this->eventBus = $this->createMock(EventBusPort::class);
    $this->handler = new CreateUserHandler(
      $this->userRepository,
      $this->uuidGenerator,
      $this->hashing,
      $this->eventBus
    );
  }
  //#endregion

  //#region Methods
  /**
   * Method testCreatesANewUser
   *
   * Tests that the handler creates
   * a new user successfully.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  #[Test]
  public function testCreatesANewUser(): void
  {
    // Arrange
    $command = new CreateUserCommand(
      username: 'jdoe',
      email: 'jdoe@example.com',
      password: 'password123',
      firstName: 'John',
      lastName: 'Doe'
    );

    $userId = '123e4567-e89b-12d3-a456-426614174000';
    $hashedPassword = new HashedSecret('$2y$10$hashedpassword');

    $this->uuidGenerator->expects($this->once())
      ->method('generate')
      ->willReturn($userId);

    $this->hashing->expects($this->once())
      ->method('hash')
      ->with('password123')
      ->willReturn($hashedPassword);

    $this->userRepository->expects($this->once())
      ->method('save')
      ->with($this->isInstanceOf(User::class));

    $this->eventBus->expects($this->once())
      ->method('publish');

    // Act
    $result = ($this->handler)($command);

    // Assert
    $this->assertInstanceOf(CreateUserResult::class, $result);
    $this->assertEquals($userId, $result->userId);
  }
  //#endregion
}
