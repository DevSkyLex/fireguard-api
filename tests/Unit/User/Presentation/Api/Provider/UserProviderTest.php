<?php

declare(strict_types=1);

namespace Tests\Unit\User\Presentation\Api\Provider;

use ApiPlatform\Metadata\Get;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Domain\ValueObject\Email;
use User\Application\UseCase\Query\GetUser\GetUserQuery;
use User\Application\UseCase\Query\GetUser\GetUserResult;
use User\Domain\Model\User;
use User\Domain\ValueObject\HashedPassword;
use User\Domain\ValueObject\UserId;
use User\Domain\ValueObject\Username;
use User\Domain\ValueObject\UserProfile;
use User\Presentation\Api\Dto\UserOutput;
use User\Presentation\Api\Provider\UserProvider;

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
   * Property queryBus
   *
   * Mock of the query bus.
   *
   * @access private
   *
   * @var QueryBusPort&MockObject
   */
  private QueryBusPort&MockObject $queryBus;

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
    $this->queryBus = $this->createMock(QueryBusPort::class);
    $this->provider = new UserProvider($this->queryBus);
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

    $result = new GetUserResult($user);

    $this->queryBus->expects($this->once())
      ->method('ask')
      ->with($this->callback(fn(GetUserQuery $query) => $query->id === $id->value))
      ->willReturn($result);

    $operation = new Get();
    $uriVariables = ['id' => $id->value];

    // Act
    $response = $this->provider->provide($operation, $uriVariables);

    // Assert
    $this->assertInstanceOf(UserOutput::class, $response);
    $this->assertEquals($id->value, $response->id);
    $this->assertEquals('jdoe', $response->username);
    $this->assertEquals('John', $response->firstName);
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
    $result = new GetUserResult(null);

    $this->queryBus->method('ask')->willReturn($result);

    $operation = new Get();
    $uriVariables = ['id' => $id->value];

    // Act
    $response = $this->provider->provide($operation, $uriVariables);

    // Assert
    $this->assertNull($response);
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
    $response = $this->provider->provide($operation, $uriVariables);

    // Assert
    $this->assertNull($response);
  }
  //#endregion
}
