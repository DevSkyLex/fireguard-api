<?php

declare(strict_types=1);

namespace Tests\Unit\User\Presentation\Api\Provider;

use ApiPlatform\Metadata\Get;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Email;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Model\User;
use User\Domain\ValueObject\HashedPassword;
use User\Domain\ValueObject\UserId;
use User\Domain\ValueObject\Username;
use User\Domain\ValueObject\UserProfile;
use User\Presentation\Api\Provider\UserProvider;
use User\Presentation\Api\Resource\UserResource;

/**
 * Test UserProviderTest
 * @final
 *
 * Unit tests for the UserProvider.
 *
 * @category Provider Tests
 * @package Tests\Unit\User\Presentation\Api\Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UserProvider::class)]
final class UserProviderTest extends TestCase
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
   * Property provider
   *
   * The provider under test.
   *
   * @access private
   *
   * @var UserProvider
   */
  private UserProvider $provider;
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
    $this->provider = new UserProvider($this->userRepository);
  }
  //#endregion

  //#region Methods
  /**
   * Method testProvidesUserResource
   *
   * Tests that the provider provides
   * a user resource successfully.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  #[Test]
  public function testProvidesUserResource(): void
  {
    // Arrange
    $id = UserId::generate();
    $user = User::register(
      $id,
      new Username('jdoe'),
      new Email('jdoe@example.com'),
      HashedPassword::fromPlain('password123'),
      new UserProfile('John', 'Doe')
    );

    $this->userRepository->expects($this->once())
      ->method('findById')
      ->with($this->callback(fn(UserId $uid) => $uid->equals($id)))
      ->willReturn($user);

    $operation = new Get();
    $uriVariables = ['id' => $id->value];

    // Act
    $result = $this->provider->provide($operation, $uriVariables);

    // Assert
    $this->assertInstanceOf(UserResource::class, $result);
    $this->assertEquals($id->value, $result->id);
    $this->assertEquals('jdoe', $result->username);
    $this->assertEquals('John', $result->firstName);
  }

  /**
   * Method testReturnsNullIfUserNotFound
   *
   * Tests that the provider returns null
   * when user is not found.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  #[Test]
  public function testReturnsNullIfUserNotFound(): void
  {
    // Arrange
    $id = UserId::generate();
    $this->userRepository->method('findById')->willReturn(null);

    $operation = new Get();
    $uriVariables = ['id' => $id->value];

    // Act
    $result = $this->provider->provide($operation, $uriVariables);

    // Assert
    $this->assertNull($result);
  }

  /**
   * Method testReturnsNullIfIdMissing
   *
   * Tests that the provider returns null
   * when id is missing.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  #[Test]
  public function testReturnsNullIfIdMissing(): void
  {
    // Arrange
    $operation = new Get();
    $uriVariables = [];

    // Act
    $result = $this->provider->provide($operation, $uriVariables);

    // Assert
    $this->assertNull($result);
  }
  //#endregion
}
