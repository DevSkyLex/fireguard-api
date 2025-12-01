<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use Auth\Application\Port\Outbound\JwtTokenServicePort;
use Auth\Presentation\Api\Dto\LoginOutput;
use Auth\Presentation\Api\Port\RefreshTokenCookieServicePort;
use Auth\Presentation\Api\Processor\RefreshTokenProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use User\Application\Port\Outbound\UserRepositoryPort;
use Tests\Support\Factory\UserTestFactory;

/**
 * Class RefreshTokenProcessorTest
 *
 * Unit tests for the RefreshTokenProcessor.
 *
 * @category Unit Test
 * @package Tests\Unit\Auth\Presentation\Api\Processor
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Auth\Presentation\Api\Processor\RefreshTokenProcessor
 */
#[CoversClass(className: RefreshTokenProcessor::class)]
final class RefreshTokenProcessorTest extends TestCase
{
  //#region Properties
  /**
   * Property tokenService
   *
   * Mock of the JwtTokenServicePort.
   *
   * @access private
   *
   * @var MockObject&JwtTokenServicePort
   */
  private MockObject&JwtTokenServicePort $tokenService;

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
   * Property userRepository
   *
   * Mock of the UserRepositoryPort.
   *
   * @access private
   *
   * @var MockObject&UserRepositoryPort
   */
  private MockObject&UserRepositoryPort $userRepository;

  /**
   * Property processor
   *
   * Instance of the RefreshTokenProcessor class.
   *
   * @access private
   *
   * @var RefreshTokenProcessor
   */
  private RefreshTokenProcessor $processor;
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
    $this->tokenService = $this->createMock(JwtTokenServicePort::class);
    $this->requestStack = $this->createMock(RequestStack::class);
    $this->cookieService = $this->createMock(RefreshTokenCookieServicePort::class);
    $this->userRepository = $this->createMock(UserRepositoryPort::class);

    $this->processor = new RefreshTokenProcessor(
      tokenService: $this->tokenService,
      requestStack: $this->requestStack,
      cookieService: $this->cookieService,
      userRepository: $this->userRepository
    );
  }

  /**
   * Method testProcessReturnsNullWhenNoCurrentRequest
   *
   * Tests that the processor returns null when
   * there is no current request.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessReturnsNullWhenNoCurrentRequest(): void
  {
    $operation = $this->createMock(Operation::class);

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn(null);

    $result = $this->processor->process(null, $operation);

    $this->assertNull($result);
  }

  /**
   * Method testProcessThrowsUnauthorizedWhenNoRefreshToken
   *
   * Tests that the processor throws UnauthorizedHttpException
   * when no refresh token.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessThrowsUnauthorizedWhenNoRefreshToken(): void
  {
    $operation = $this->createMock(Operation::class);
    $request = Request::create('/api/auth/refresh', 'POST');

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $this->cookieService
      ->expects($this->once())
      ->method('getRefreshTokenFromRequest')
      ->with($request)
      ->willReturn(null);

    $this->expectException(UnauthorizedHttpException::class);
    $this->expectExceptionMessage('No refresh token provided');

    $this->processor->process(null, $operation);
  }

  /**
   * Method testProcessThrowsUnauthorizedWhenRefreshTokenEmpty
   *
   * Tests that the processor throws UnauthorizedHttpException when refresh token is empty.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessThrowsUnauthorizedWhenRefreshTokenEmpty(): void
  {
    $operation = $this->createMock(Operation::class);
    $request = Request::create('/api/auth/refresh', 'POST');

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $this->cookieService
      ->expects($this->once())
      ->method('getRefreshTokenFromRequest')
      ->with($request)
      ->willReturn('');

    $this->expectException(UnauthorizedHttpException::class);
    $this->expectExceptionMessage('No refresh token provided');

    $this->processor->process(null, $operation);
  }

  /**
   * Method testProcessThrowsUnauthorizedWhenRefreshTokenInvalid
   *
   * Tests that the processor throws UnauthorizedHttpException when refresh token is invalid.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessThrowsUnauthorizedWhenRefreshTokenInvalid(): void
  {
    $operation = $this->createMock(Operation::class);
    $request = Request::create('/api/auth/refresh', 'POST');

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $this->cookieService
      ->expects($this->once())
      ->method('getRefreshTokenFromRequest')
      ->with($request)
      ->willReturn('invalid_token');

    $this->tokenService
      ->expects($this->once())
      ->method('decodeRefreshToken')
      ->with('invalid_token')
      ->willReturn(null);

    $this->expectException(UnauthorizedHttpException::class);
    $this->expectExceptionMessage('Invalid or expired refresh token');

    $this->processor->process(null, $operation);
  }

  /**
   * Method testProcessThrowsUnauthorizedWhenUserNotFound
   *
   * Tests that the processor throws UnauthorizedHttpException when user not found.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessThrowsUnauthorizedWhenUserNotFound(): void
  {
    $operation = $this->createMock(Operation::class);
    $request = Request::create('/api/auth/refresh', 'POST');

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $this->cookieService
      ->expects($this->once())
      ->method('getRefreshTokenFromRequest')
      ->with($request)
      ->willReturn('valid_token');

    $this->tokenService
      ->expects($this->once())
      ->method('decodeRefreshToken')
      ->with('valid_token')
      ->willReturn([
        'refresh_token_id' => 'refresh-123',
        'access_token_id' => 'access-123',
        'user_id' => '550e8400-e29b-41d4-a716-446655440000',
        'scopes' => ['READ', 'WRITE'],
        'expires_at' => time() + 3600,
      ]);

    $this->userRepository
      ->expects($this->once())
      ->method('findById')
      ->willReturn(null);

    $this->expectException(UnauthorizedHttpException::class);
    $this->expectExceptionMessage('User account is not active');

    $this->processor->process(null, $operation);
  }

  /**
   * Method testProcessReturnsLoginOutputOnSuccess
   *
   * Tests that the processor returns a LoginOutput on successful refresh.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessReturnsLoginOutputOnSuccess(): void
  {
    $operation = $this->createMock(Operation::class);
    $request = Request::create('/api/auth/refresh', 'POST');

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $this->cookieService
      ->expects($this->once())
      ->method('getRefreshTokenFromRequest')
      ->with($request)
      ->willReturn('valid_token');

    $this->tokenService
      ->expects($this->once())
      ->method('decodeRefreshToken')
      ->with('valid_token')
      ->willReturn([
        'refresh_token_id' => 'refresh-123',
        'access_token_id' => 'access-123',
        'user_id' => '550e8400-e29b-41d4-a716-446655440000',
        'scopes' => ['READ', 'WRITE'],
        'expires_at' => time() + 3600,
      ]);

    // Create a real User instance using the factory
    $user = UserTestFactory::createActive(
      id: '550e8400-e29b-41d4-a716-446655440000',
      email: 'test@example.com'
    );

    $this->userRepository
      ->expects($this->once())
      ->method('findById')
      ->willReturn($user);

    $this->tokenService
      ->expects($this->once())
      ->method('generateTokens')
      ->with('550e8400-e29b-41d4-a716-446655440000', 'test@example.com', ['READ', 'WRITE'])
      ->willReturn([
        'access_token' => 'new_access_token',
        'refresh_token' => 'new_refresh_token',
        'token_type' => 'Bearer',
        'expires_in' => 3600,
      ]);

    $this->cookieService
      ->expects($this->once())
      ->method('createCookie')
      ->with('new_refresh_token')
      ->willReturn(Cookie::create('refresh_token', 'new_refresh_token'));

    $result = $this->processor->process(null, $operation);

    $this->assertInstanceOf(LoginOutput::class, $result);
    $this->assertEquals('new_access_token', $result->accessToken);
    $this->assertEquals('Bearer', $result->tokenType);
    $this->assertEquals(3600, $result->expiresIn);
    $this->assertEquals('READ WRITE', $result->scope);
  }

  /**
   * Method testProcessThrowsUnauthorizedWhenUserCannotLogin
   *
   * Tests that the processor throws UnauthorizedHttpException when user cannot login.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessThrowsUnauthorizedWhenUserCannotLogin(): void
  {
    $operation = $this->createMock(Operation::class);
    $request = Request::create('/api/auth/refresh', 'POST');

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $this->cookieService
      ->expects($this->once())
      ->method('getRefreshTokenFromRequest')
      ->with($request)
      ->willReturn('valid_token');

    $this->tokenService
      ->expects($this->once())
      ->method('decodeRefreshToken')
      ->with('valid_token')
      ->willReturn([
        'refresh_token_id' => 'refresh-123',
        'access_token_id' => 'access-123',
        'user_id' => '550e8400-e29b-41d4-a716-446655440001',
        'scopes' => ['READ'],
        'expires_at' => time() + 3600,
      ]);

    // Create a pending user (not verified, cannot login)
    $user = UserTestFactory::createPending(
      id: '550e8400-e29b-41d4-a716-446655440001',
      email: 'pending@example.com'
    );

    $this->userRepository
      ->expects($this->once())
      ->method('findById')
      ->willReturn($user);

    $this->expectException(UnauthorizedHttpException::class);
    $this->expectExceptionMessage('User account is not active');

    $this->processor->process(null, $operation);
  }

  /**
   * Method testProcessThrowsUnauthorizedWhenUserIdIsEmpty
   *
   * Tests that the processor throws UnauthorizedHttpException when user_id is empty.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessThrowsUnauthorizedWhenUserIdIsEmpty(): void
  {
    $operation = $this->createMock(Operation::class);
    $request = Request::create('/api/auth/refresh', 'POST');

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $this->cookieService
      ->expects($this->once())
      ->method('getRefreshTokenFromRequest')
      ->with($request)
      ->willReturn('valid_token');

    $this->tokenService
      ->expects($this->once())
      ->method('decodeRefreshToken')
      ->with('valid_token')
      ->willReturn([
        'refresh_token_id' => 'refresh-123',
        'access_token_id' => 'access-123',
        'user_id' => '',
        'scopes' => ['READ'],
        'expires_at' => time() + 3600,
      ]);

    $this->expectException(UnauthorizedHttpException::class);

    $this->processor->process(null, $operation);
  }
  //#endregion
}
