<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use Auth\Presentation\Api\Port\RefreshTokenCookieServicePort;
use Auth\Presentation\Api\Processor\LogoutProcessor;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class LogoutProcessorTest
 *
 * Unit tests for the LogoutProcessor.
 *
 * @category Unit Test
 * @package Tests\Unit\Auth\Presentation\Api\Processor
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Auth\Presentation\Api\Processor\LogoutProcessor
 */
#[CoversClass(className: LogoutProcessor::class)]
final class LogoutProcessorTest extends TestCase
{
  //#region Properties
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
   * Property cookieService
   *
   * Mock of the RefreshTokenCookieServicePort.
   *
   * @access private
   *
   * @var MockObject&RefreshTokenCookieServicePort
   */
  private MockObject&RefreshTokenCookieServicePort $cookieService;

  /**
   * Property accessTokenRepository
   *
   * Mock of the AccessTokenRepositoryInterface.
   *
   * @access private
   *
   * @var MockObject&AccessTokenRepositoryInterface
   */
  private MockObject&AccessTokenRepositoryInterface $accessTokenRepository;

  /**
   * Property refreshTokenRepository
   *
   * Mock of the RefreshTokenRepositoryInterface.
   *
   * @access private
   *
   * @var MockObject&RefreshTokenRepositoryInterface
   */
  private MockObject&RefreshTokenRepositoryInterface $refreshTokenRepository;

  /**
   * Property processor
   *
   * Instance of the LogoutProcessor class.
   *
   * @access private
   *
   * @var LogoutProcessor
   */
  private LogoutProcessor $processor;
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
    $this->requestStack = $this->createMock(RequestStack::class);
    $this->cookieService = $this->createMock(RefreshTokenCookieServicePort::class);
    $this->accessTokenRepository = $this->createMock(AccessTokenRepositoryInterface::class);
    $this->refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);

    $this->processor = new LogoutProcessor(
      requestStack: $this->requestStack,
      cookieService: $this->cookieService,
      accessTokenRepository: $this->accessTokenRepository,
      refreshTokenRepository: $this->refreshTokenRepository,
      encryptionKey: base64_encode(random_bytes(32))
    );
  }

  /**
   * Method testProcessReturnsJsonResponseWhenNoRequest
   *
   * Tests that the processor returns a JsonResponse even when no request.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessReturnsJsonResponseWhenNoRequest(): void
  {
    $operation = $this->createMock(Operation::class);

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn(null);

    $result = $this->processor->process(null, $operation);

    $this->assertInstanceOf(JsonResponse::class, $result);
    $this->assertEquals(Response::HTTP_OK, $result->getStatusCode());
  }

  /**
   * Method testProcessReturnsSuccessMessage
   *
   * Tests that the processor returns a success message.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessReturnsSuccessMessage(): void
  {
    $operation = $this->createMock(Operation::class);
    $request = Request::create('/api/auth/logout', 'POST');

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $this->cookieService
      ->expects($this->once())
      ->method('getRefreshTokenFromRequest')
      ->with($request)
      ->willReturn(null);

    $this->cookieService
      ->expects($this->once())
      ->method('createClearCookie')
      ->willReturn(Cookie::create('refresh_token', ''));

    $result = $this->processor->process(null, $operation);

    $this->assertInstanceOf(JsonResponse::class, $result);
    $this->assertEquals(Response::HTTP_OK, $result->getStatusCode());

    $content = json_decode($result->getContent() ?: '{}', true);
    $this->assertArrayHasKey('message', $content);
    $this->assertEquals('Logged out successfully', $content['message']);
  }

  /**
   * Method testProcessClearsCookie
   *
   * Tests that the processor clears the refresh token cookie.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessClearsCookie(): void
  {
    $operation = $this->createMock(Operation::class);
    $request = Request::create('/api/auth/logout', 'POST');

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $this->cookieService
      ->expects($this->once())
      ->method('getRefreshTokenFromRequest')
      ->with($request)
      ->willReturn(null);

    $clearCookie = Cookie::create('refresh_token', '');

    $this->cookieService
      ->expects($this->once())
      ->method('createClearCookie')
      ->willReturn($clearCookie);

    $this->processor->process(null, $operation);

    $this->assertEquals($clearCookie, $request->attributes->get('_refresh_token_cookie'));
  }

  /**
   * Method testProcessHandlesInvalidRefreshToken
   *
   * Tests that the processor handles invalid refresh tokens gracefully.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessHandlesInvalidRefreshToken(): void
  {
    $operation = $this->createMock(Operation::class);
    $request = Request::create('/api/auth/logout', 'POST');

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    // Return an invalid encrypted token that will fail to decrypt
    $this->cookieService
      ->expects($this->once())
      ->method('getRefreshTokenFromRequest')
      ->with($request)
      ->willReturn('invalid_encrypted_token');

    $this->cookieService
      ->expects($this->once())
      ->method('createClearCookie')
      ->willReturn(Cookie::create('refresh_token', ''));

    // Should not throw, just ignore the invalid token
    $result = $this->processor->process(null, $operation);

    $this->assertInstanceOf(JsonResponse::class, $result);
    $this->assertEquals(Response::HTTP_OK, $result->getStatusCode());
  }

  /**
   * Method testProcessHandlesInvalidAccessToken
   *
   * Tests that the processor handles invalid access tokens gracefully.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessHandlesInvalidAccessToken(): void
  {
    $operation = $this->createMock(Operation::class);
    $request = Request::create('/api/auth/logout', 'POST');
    $request->headers->set('Authorization', 'Bearer invalid_jwt_token');

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $this->cookieService
      ->expects($this->once())
      ->method('getRefreshTokenFromRequest')
      ->with($request)
      ->willReturn(null);

    $this->cookieService
      ->expects($this->once())
      ->method('createClearCookie')
      ->willReturn(Cookie::create('refresh_token', ''));

    // Should not throw, just ignore the invalid token
    $result = $this->processor->process(null, $operation);

    $this->assertInstanceOf(JsonResponse::class, $result);
    $this->assertEquals(Response::HTTP_OK, $result->getStatusCode());
  }

  /**
   * Method testProcessWithEmptyAuthorizationHeader
   *
   * Tests that the processor handles empty authorization header.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessWithEmptyAuthorizationHeader(): void
  {
    $operation = $this->createMock(Operation::class);
    $request = Request::create('/api/auth/logout', 'POST');
    $request->headers->set('Authorization', '');

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $this->cookieService
      ->expects($this->once())
      ->method('getRefreshTokenFromRequest')
      ->with($request)
      ->willReturn(null);

    $this->cookieService
      ->expects($this->once())
      ->method('createClearCookie')
      ->willReturn(Cookie::create('refresh_token', ''));

    $result = $this->processor->process(null, $operation);

    $this->assertInstanceOf(JsonResponse::class, $result);
    $this->assertEquals(Response::HTTP_OK, $result->getStatusCode());
  }

  /**
   * Method testProcessWithNonBearerAuthorizationHeader
   *
   * Tests that the processor ignores non-Bearer authorization headers.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessWithNonBearerAuthorizationHeader(): void
  {
    $operation = $this->createMock(Operation::class);
    $request = Request::create('/api/auth/logout', 'POST');
    $request->headers->set('Authorization', 'Basic dXNlcjpwYXNz');

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $this->cookieService
      ->expects($this->once())
      ->method('getRefreshTokenFromRequest')
      ->with($request)
      ->willReturn(null);

    $this->cookieService
      ->expects($this->once())
      ->method('createClearCookie')
      ->willReturn(Cookie::create('refresh_token', ''));

    // Access token repository should NOT be called for non-Bearer auth
    $this->accessTokenRepository
      ->expects($this->never())
      ->method('revokeAccessToken');

    $result = $this->processor->process(null, $operation);

    $this->assertInstanceOf(JsonResponse::class, $result);
    $this->assertEquals(Response::HTTP_OK, $result->getStatusCode());
  }

  /**
   * Method testProcessWithEmptyRefreshToken
   *
   * Tests that the processor handles empty refresh token string.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessWithEmptyRefreshToken(): void
  {
    $operation = $this->createMock(Operation::class);
    $request = Request::create('/api/auth/logout', 'POST');

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $this->cookieService
      ->expects($this->once())
      ->method('getRefreshTokenFromRequest')
      ->with($request)
      ->willReturn('');

    $this->cookieService
      ->expects($this->once())
      ->method('createClearCookie')
      ->willReturn(Cookie::create('refresh_token', ''));

    // Refresh token repository should NOT be called for empty token
    $this->refreshTokenRepository
      ->expects($this->never())
      ->method('revokeRefreshToken');

    $result = $this->processor->process(null, $operation);

    $this->assertInstanceOf(JsonResponse::class, $result);
    $this->assertEquals(Response::HTTP_OK, $result->getStatusCode());
  }

  /**
   * Method testProcessResponseContentType
   *
   * Tests that the processor returns JSON content type.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessResponseContentType(): void
  {
    $operation = $this->createMock(Operation::class);
    $request = Request::create('/api/auth/logout', 'POST');

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $this->cookieService
      ->expects($this->once())
      ->method('getRefreshTokenFromRequest')
      ->willReturn(null);

    $this->cookieService
      ->expects($this->once())
      ->method('createClearCookie')
      ->willReturn(Cookie::create('refresh_token', ''));

    $result = $this->processor->process(null, $operation);

    $this->assertInstanceOf(JsonResponse::class, $result);
    $this->assertStringContainsString('application/json', $result->headers->get('Content-Type') ?? '');
  }

  /**
   * Method testProcessAlwaysReturnsOkStatus
   *
   * Tests that logout always returns 200 OK regardless of token validity.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessAlwaysReturnsOkStatus(): void
  {
    $operation = $this->createMock(Operation::class);
    $request = Request::create('/api/auth/logout', 'POST');
    $request->headers->set('Authorization', 'Bearer malformed.jwt.token');

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $this->cookieService
      ->expects($this->once())
      ->method('getRefreshTokenFromRequest')
      ->willReturn('also_invalid_token');

    $this->cookieService
      ->expects($this->once())
      ->method('createClearCookie')
      ->willReturn(Cookie::create('refresh_token', ''));

    $result = $this->processor->process(null, $operation);

    // Even with invalid tokens, logout should succeed
    $this->assertEquals(Response::HTTP_OK, $result->getStatusCode());
  }
  //#endregion
}
