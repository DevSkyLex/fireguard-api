<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use Auth\Presentation\Api\Dto\TokenInput;
use Auth\Presentation\Api\Dto\TokenOutput;
use Auth\Presentation\Api\Processor\IssueTokenProcessor;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Response as Psr7Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use stdClass;

/**
 * Class IssueTokenProcessorTest
 *
 * Unit tests for the IssueTokenProcessor.
 *
 * @category Unit Test
 * @package Tests\Unit\Auth\Presentation\Api\Processor
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Auth\Presentation\Api\Processor\IssueTokenProcessor
 */
#[CoversClass(className: IssueTokenProcessor::class)]
final class IssueTokenProcessorTest extends TestCase
{
  //#region Properties
  /**
   * Property authorizationServer
   *
   * Mock of the AuthorizationServer.
   *
   * @access private
   *
   * @var MockObject&AuthorizationServer
   */
  private MockObject&AuthorizationServer $authorizationServer;

  /**
   * Property requestStack
   *
   * Mock of the RequestStack.
   *
   * @access private
   *
   * @var MockObject&RequestStack
   */
  private MockObject&RequestStack $requestStack;

  /**
   * Property processor
   *
   * Instance of the IssueTokenProcessor class.
   *
   * @access private
   *
   * @var IssueTokenProcessor
   */
  private IssueTokenProcessor $processor;
  //#endregion

  //#region Methods
  /**
   * Method setUp
   *
   * Sets up the test environment.
   *
   * @access protected
   *
   * @return void No return value.
   */
  protected function setUp(): void
  {
    $this->authorizationServer = $this->createMock(AuthorizationServer::class);
    $this->requestStack = $this->createMock(RequestStack::class);
    $this->processor = new IssueTokenProcessor(
      $this->authorizationServer,
      $this->requestStack
    );
  }

  /**
   * Method testProcessReturnsNullWhenDataIsNotTokenInput
   *
   * Tests that the processor returns null when data is not a TokenInput.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessReturnsNullWhenDataIsNotTokenInput(): void
  {
    $operation = $this->createMock(Operation::class);

    $result = $this->processor->process(new stdClass(), $operation);

    $this->assertNull($result);
  }

  /**
   * Method testProcessReturnsNullWhenNoCurrentRequest
   *
   * Tests that the processor returns null when there is no current request.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessReturnsNullWhenNoCurrentRequest(): void
  {
    $tokenInput = new TokenInput();
    $tokenInput->grantType = 'client_credentials';
    $tokenInput->clientId = 'client_id';
    $tokenInput->clientSecret = 'client_secret';

    $operation = $this->createMock(Operation::class);

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn(null);

    $result = $this->processor->process($tokenInput, $operation);

    $this->assertNull($result);
  }

  /**
   * Method testProcessReturnsTokenOutputOnSuccess
   *
   * Tests that the processor returns a TokenOutput on successful token issuance.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessReturnsTokenOutputOnSuccess(): void
  {
    $tokenInput = new TokenInput();
    $tokenInput->grantType = 'client_credentials';
    $tokenInput->clientId = 'client_id';
    $tokenInput->clientSecret = 'client_secret';
    $tokenInput->scope = 'read';

    $operation = $this->createMock(Operation::class);

    $request = Request::create(
      uri: '/oauth2/token',
      method: 'POST',
      content: json_encode([
        'grant_type' => 'client_credentials',
        'client_id' => 'client_id',
        'client_secret' => 'client_secret',
        'scope' => 'read',
      ]) ?: ''
    );

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $responseBody = json_encode([
      'access_token' => 'access_token_value',
      'token_type' => 'Bearer',
      'expires_in' => 3600,
      'refresh_token' => 'refresh_token_value',
      'scope' => 'read',
    ]);

    $streamMock = $this->createMock(StreamInterface::class);
    $streamMock
      ->method('__toString')
      ->willReturn($responseBody ?: '');

    $psrResponse = $this->createMock(ResponseInterface::class);
    $psrResponse
      ->method('getBody')
      ->willReturn($streamMock);

    $this->authorizationServer
      ->expects($this->once())
      ->method('respondToAccessTokenRequest')
      ->willReturn($psrResponse);

    $result = $this->processor->process($tokenInput, $operation);

    $this->assertInstanceOf(TokenOutput::class, $result);
    $this->assertEquals('access_token_value', $result->accessToken);
    $this->assertEquals('Bearer', $result->tokenType);
    $this->assertEquals(3600, $result->expiresIn);
    $this->assertEquals('refresh_token_value', $result->refreshToken);
    $this->assertEquals('read', $result->scope);
  }

  /**
   * Method testProcessThrowsBadRequestOnOAuthException
   *
   * Tests that the processor throws a BadRequestHttpException on OAuth error.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessThrowsBadRequestOnOAuthException(): void
  {
    $tokenInput = new TokenInput();
    $tokenInput->grantType = 'client_credentials';
    $tokenInput->clientId = 'invalid_client';
    $tokenInput->clientSecret = 'invalid_secret';

    $operation = $this->createMock(Operation::class);

    $request = Request::create(
      uri: '/oauth2/token',
      method: 'POST'
    );

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $psrRequest = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
    $oauthException = OAuthServerException::invalidClient($psrRequest);

    $this->authorizationServer
      ->expects($this->once())
      ->method('respondToAccessTokenRequest')
      ->willThrowException($oauthException);

    $this->expectException(BadRequestHttpException::class);

    $this->processor->process($tokenInput, $operation);
  }
  //#endregion
}
