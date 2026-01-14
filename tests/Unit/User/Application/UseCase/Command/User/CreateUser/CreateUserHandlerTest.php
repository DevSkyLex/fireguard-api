<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\UseCase\Command\User\CreateUser;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{EventBusPort, HashingPort};
use Shared\Domain\ValueObject\HashedSecret;
use Tests\Helper\TestEventIdProvider;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Application\UseCase\Command\User\CreateUser\{CreateUserCommand, CreateUserHandler, CreateUserResult};
use User\Domain\Model\User\User;
use User\Domain\ValueObject\UserId;

/**
 * Test CreateUserHandlerTest.
 *
 * @category Handler Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateUserHandler::class)]
final class CreateUserHandlerTest extends TestCase
{
  // #region Properties
  /**
   * Property userRepository.
   *
   * Mock of the user repository.
   */
  private UserRepositoryPort&MockObject $userRepository;

  /**
   * Property uuidFactory.
   *
   * Mock of the UUID factory.
   */
  private UuidFactory&MockObject $uuidFactory;

  /**
   * Property eventIdProvider.
   *
   * Event ID provider for tests.
   */
  private TestEventIdProvider $eventIdProvider;

  /**
   * Property hashing.
   *
   * Mock of the hashing service.
   */
  private HashingPort&MockObject $hashing;

  /**
   * Property eventBus.
   *
   * Mock of the event bus.
   */
  private EventBusPort&MockObject $eventBus;

  /**
   * Property handler.
   *
   * The handler under test.
   */
  private CreateUserHandler $handler;
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
    $this->uuidFactory = $this->createMock(UuidFactory::class);
    $this->hashing = $this->createMock(HashingPort::class);
    $this->eventBus = $this->createMock(EventBusPort::class);
    $this->eventIdProvider = new TestEventIdProvider();
    $this->handler = new CreateUserHandler(
      userRepository: $this->userRepository,
      uuidFactory: $this->uuidFactory,
      hashing: $this->hashing,
      eventBus: $this->eventBus,
      eventIdProvider: $this->eventIdProvider,
    );
  }
  // #endregion

  // #region Methods
  /**
   * Method testCreatesANewUser.
   *
   * Tests that the handler creates
   * a new user successfully.
   *
   * @since 1.0.0
   *
   * @return void no return value
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
      lastName: 'Doe',
    );

    $userId = '123e4567-e89b-12d3-a456-426614174000';
    $hashedPassword = new HashedSecret('$2y$10$hashedpassword');

    $this->uuidFactory->expects($this->once())
      ->method('create')
      ->with(UserId::class)
      ->willReturn(new UserId($userId));

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
  // #endregion
}
