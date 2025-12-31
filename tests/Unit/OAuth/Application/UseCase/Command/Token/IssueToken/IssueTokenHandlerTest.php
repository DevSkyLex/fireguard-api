<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Application\UseCase\Command\Token\IssueToken;

use OAuth\Application\Port\Outbound\Token\AccessTokenRepositoryPort;
use OAuth\Application\Port\Outbound\Token\AuthCodeRepositoryPort;
use OAuth\Application\Port\Outbound\Token\AuthorizationServerPort;
use OAuth\Application\Port\Outbound\Token\IdTokenIssuerPort;
use OAuth\Application\Port\Outbound\Token\RefreshTokenRepositoryPort;
use OAuth\Application\Service\OidcClaimsBuilderInterface;
use OAuth\Application\UseCase\Command\Token\IssueToken\IssueTokenCommand;
use OAuth\Application\UseCase\Command\Token\IssueToken\IssueTokenHandler;
use OAuth\Application\UseCase\Command\Token\IssueToken\IssueTokenResult;
use OAuth\Domain\Event\Token\TokenIssuedEvent;
use OAuth\Domain\Event\Token\TokenIssueFailedEvent;
use OAuth\Domain\Exception\Token\AuthorizationException;
use OAuth\Domain\ValueObject\Scope\Scope;
use OAuth\Domain\ValueObject\Security\GrantType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Class IssueTokenHandlerTest.
 *
 * Unit tests for the IssueTokenHandler.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \OAuth\Application\UseCase\Command\Token\IssueToken\IssueTokenHandler
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

  /**
   * Property eventDispatcher.
   *
   * Mocked event dispatcher.
   */
  private EventDispatcherPort&MockObject $eventDispatcher;
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
    $this->eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $authCodeRepository = $this->createMock(AuthCodeRepositoryPort::class);
    $idTokenIssuer = $this->createMock(IdTokenIssuerPort::class);
    $queryBus = $this->createMock(QueryBusPort::class);
    $claimsBuilder = $this->createMock(OidcClaimsBuilderInterface::class);
    $refreshTokenRepository = $this->createMock(RefreshTokenRepositoryPort::class);
    $accessTokenRepository = $this->createMock(AccessTokenRepositoryPort::class);

    $this->handler = new IssueTokenHandler(
      $this->authorizationServer,
      $this->eventDispatcher,
      $authCodeRepository,
      $idTokenIssuer,
      $queryBus,
      $claimsBuilder,
      $refreshTokenRepository,
      $accessTokenRepository,
    );
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

    $this->eventDispatcher
      ->expects($this->once())
      ->method('dispatch')
      ->with($this->isInstanceOf(TokenIssuedEvent::class));

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

    $this->eventDispatcher
      ->expects($this->once())
      ->method('dispatch')
      ->with($this->isInstanceOf(TokenIssueFailedEvent::class));

    $this->expectException(AuthorizationException::class);

    ($this->handler)($command);
  }
  // #endregion
}
