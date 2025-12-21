<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\IssueToken;

use Auth\Domain\Exception\AuthorizationException;
use OAuth\Application\Port\Outbound\AuthorizationServerPort;
use OAuth\Application\UseCase\Command\IssueToken\IssueTokenCommand;
use OAuth\Application\UseCase\Command\IssueToken\IssueTokenHandler;
use OAuth\Application\UseCase\Command\IssueToken\IssueTokenResult;
use OAuth\Domain\ValueObject\GrantType;
use OAuth\Domain\ValueObject\Scope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class IssueTokenHandlerTest.
 *
 * Unit tests for the IssueTokenHandler.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \OAuth\Application\UseCase\Command\IssueToken\IssueTokenHandler
 */
#[CoversClass(className: IssueTokenHandler::class)]
final class IssueTokenHandlerTest extends TestCase
{
  // #region Properties
  /**
   * Property authorizationServer.
   *
   * Mocked authorization server port.
   */
  private AuthorizationServerPort&MockObject $authorizationServer;

  /**
   * Property handler.
   *
   * IssueTokenHandler instance.
   */
  private IssueTokenHandler $handler;
  // #endregion

  // #region Methods
  /**
   * Method setUp.
   *
   * Sets up the test environment.
   *
   * @return void no return value
   */
  protected function setUp(): void
  {
    $this->authorizationServer = $this->createMock(AuthorizationServerPort::class);
    $this->handler = new IssueTokenHandler($this->authorizationServer);
  }

  /**
   * Method testHandleSuccessfullyIssuesToken.
   *
   * Tests that the handler successfully issues a token
   * when provided with valid credentials.
   *
   * @return void no return value
   */
  #[Test]
  public function testHandleSuccessfullyIssuesToken(): void
  {
    $command = new IssueTokenCommand(
      grantType: GrantType::CLIENT_CREDENTIALS->value,
      clientId: 'client_id',
      clientSecret: 'client_secret',
      scope: Scope::READ->value,
    );

    $expectedResult = new IssueTokenResult(
      accessToken: 'access_token_value',
      tokenType: 'Bearer',
      expiresIn: 3600,
    );

    $this->authorizationServer
      ->expects($this->once())
      ->method('issueAccessToken')
      ->with(
        $command->grantType,
        $command->clientId,
        $command->clientSecret,
        $command->scope,
        null,
        null,
        null,
        null,
      )
      ->willReturn($expectedResult);

    $result = ($this->handler)($command);

    $this->assertInstanceOf(IssueTokenResult::class, $result);
    $this->assertEquals('access_token_value', $result->accessToken);
    $this->assertEquals('Bearer', $result->tokenType);
    $this->assertEquals(3600, $result->expiresIn);
  }

  /**
   * Method testHandleThrowsExceptionOnFailure.
   *
   * Tests that the handler throws an exception
   * when the authorization server fails.
   *
   * @return void no return value
   */
  #[Test]
  public function testHandleThrowsExceptionOnFailure(): void
  {
    $command = new IssueTokenCommand(
      grantType: 'invalid_grant',
      clientId: 'client_id',
      clientSecret: 'client_secret',
    );

    $this->authorizationServer
      ->expects($this->once())
      ->method('issueAccessToken')
      ->willThrowException(AuthorizationException::invalidGrant('Invalid grant type'));

    $this->expectException(AuthorizationException::class);

    ($this->handler)($command);
  }
  // #endregion
}
