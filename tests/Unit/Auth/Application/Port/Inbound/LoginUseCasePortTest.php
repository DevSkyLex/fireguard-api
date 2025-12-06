<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\Port\Inbound;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Auth\Application\Port\Inbound\LoginUseCasePort;
use Auth\Application\UseCase\Command\Login\LoginCommand;
use Auth\Application\UseCase\Command\Login\LoginResult;

/**
 * Class LoginUseCasePortTest
 *
 * Unit tests for the LoginUseCasePort interface.
 *
 * @category Unit Test
 * @package Tests\Unit\Auth\Application\Port\Inbound
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: LoginUseCasePort::class)]
final class LoginUseCasePortTest extends TestCase
{
  //#region Methods
  /**
   * Method testPortCanBeImplemented
   *
   * Tests that the port interface can be implemented.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testPortCanBeImplemented(): void
  {
    $mock = $this->createMock(LoginUseCasePort::class);

    $command = new LoginCommand(
      email: 'test@example.com',
      password: 'password123',
      rememberMe: false,
      ipAddress: '127.0.0.1',
    );

    $expectedResult = new LoginResult(
      authenticated: true,
      userId: 'user-123',
      email: 'test@example.com',
      accessToken: 'access-token',
      refreshToken: 'refresh-token',
      tokenType: 'Bearer',
      expiresIn: 3600,
      scopes: ['openid', 'profile'],
    );

    $mock->expects($this->once())
      ->method('execute')
      ->with($command)
      ->willReturn($expectedResult);

    $result = $mock->execute($command);

    $this->assertTrue($result->authenticated);
    $this->assertEquals('user-123', $result->userId);
    $this->assertEquals('test@example.com', $result->email);
    $this->assertEquals('access-token', $result->accessToken);
  }

  /**
   * Method testPortReturnsFailedResult
   *
   * Tests that the port can return a failed result.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testPortReturnsFailedResult(): void
  {
    $mock = $this->createMock(LoginUseCasePort::class);

    $command = new LoginCommand(
      email: 'invalid@example.com',
      password: 'wrong-password',
    );

    $mock->expects($this->once())
      ->method('execute')
      ->with($command)
      ->willReturn(LoginResult::failed('Invalid credentials'));

    $result = $mock->execute($command);

    $this->assertFalse($result->authenticated);
    $this->assertEquals('Invalid credentials', $result->errorMessage);
    $this->assertNull($result->userId);
    $this->assertNull($result->accessToken);
  }
  //#endregion
}
