<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use Auth\Application\Port\Outbound\JwtTokenServicePort;
use Auth\Presentation\Api\Dto\LoginInput;
use Auth\Presentation\Api\Dto\LoginOutput;
use Auth\Presentation\Api\Port\RefreshTokenCookieServicePort;
use Auth\Presentation\Api\Processor\LoginProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use User\Application\UseCase\Query\AuthenticateUser\AuthenticateUserResult;

/**
 * Class LoginProcessorTest
 *
 * Unit tests for the LoginProcessor.
 *
 * @category Unit Test
 * @package Tests\Unit\Auth\Presentation\Api\Processor
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Auth\Presentation\Api\Processor\LoginProcessor
 */
#[CoversClass(className: LoginProcessor::class)]
final class LoginProcessorTest extends TestCase
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
   * Property queryBus
   *
   * Mock of the QueryBusPort.
   *
   * @access private
   *
   * @var MockObject&QueryBusPort
   */
  private MockObject&QueryBusPort $queryBus;

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
   * Property processor
   *
   * Instance of the LoginProcessor class.
   *
   * @access private
   *
   * @var LoginProcessor
   */
  private LoginProcessor $processor;
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
    $this->queryBus = $this->createMock(QueryBusPort::class);
    $this->cookieService = $this->createMock(RefreshTokenCookieServicePort::class);

    $this->processor = new LoginProcessor(
      tokenService: $this->tokenService,
      requestStack: $this->requestStack,
      queryBus: $this->queryBus,
      cookieService: $this->cookieService
    );
  }

  /**
   * Method testProcessReturnsNullWhenDataIsNotLoginInput
   *
   * Tests that the processor returns null when data is not a LoginInput.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessReturnsNullWhenDataIsNotLoginInput(): void
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
    $loginInput = new LoginInput();
    $loginInput->email = 'test@example.com';
    $loginInput->password = 'password123';

    $operation = $this->createMock(Operation::class);

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn(null);

    $result = $this->processor->process($loginInput, $operation);

    $this->assertNull($result);
  }

  /**
   * Method testProcessReturnsLoginOutputOnSuccess
   *
   * Tests that the processor returns a LoginOutput on successful login.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessReturnsLoginOutputOnSuccess(): void
  {
    $loginInput = new LoginInput();
    $loginInput->email = 'test@example.com';
    $loginInput->password = 'password123';

    $operation = $this->createMock(Operation::class);
    $request = Request::create('/api/auth/login', 'POST');

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $authResult = new AuthenticateUserResult(
      authenticated: true,
      userId: 'user-123',
      email: 'test@example.com',
      fullName: 'Test User'
    );

    $this->queryBus
      ->expects($this->once())
      ->method('ask')
      ->willReturn($authResult);

    $this->tokenService
      ->expects($this->once())
      ->method('generateTokens')
      ->with('user-123', 'test@example.com', $this->anything())
      ->willReturn([
        'access_token' => 'access_token_value',
        'refresh_token' => 'refresh_token_value',
        'token_type' => 'Bearer',
        'expires_in' => 3600,
      ]);

    $this->cookieService
      ->expects($this->once())
      ->method('createCookie')
      ->willReturn(new \Symfony\Component\HttpFoundation\Cookie('refresh_token', 'value'));

    $result = $this->processor->process($loginInput, $operation);

    $this->assertInstanceOf(LoginOutput::class, $result);
    $this->assertEquals('access_token_value', $result->accessToken);
    $this->assertEquals('Bearer', $result->tokenType);
    $this->assertEquals(3600, $result->expiresIn);
  }

  /**
   * Method testProcessThrowsUnauthorizedWhenCredentialsInvalid
   *
   * Tests that the processor throws UnauthorizedHttpException on invalid credentials.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessThrowsUnauthorizedWhenCredentialsInvalid(): void
  {
    $loginInput = new LoginInput();
    $loginInput->email = 'test@example.com';
    $loginInput->password = 'wrong_password';

    $operation = $this->createMock(Operation::class);
    $request = Request::create('/api/auth/login', 'POST');

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $authResult = new AuthenticateUserResult(
      authenticated: false
    );

    $this->queryBus
      ->expects($this->once())
      ->method('ask')
      ->willReturn($authResult);

    $this->expectException(UnauthorizedHttpException::class);

    $this->processor->process($loginInput, $operation);
  }

  /**
   * Method testProcessThrowsUnauthorizedWhenUserIdIsNull
   *
   * Tests that the processor throws UnauthorizedHttpException when userId is null.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessThrowsUnauthorizedWhenUserIdIsNull(): void
  {
    $loginInput = new LoginInput();
    $loginInput->email = 'test@example.com';
    $loginInput->password = 'password123';

    $operation = $this->createMock(Operation::class);
    $request = Request::create('/api/auth/login', 'POST');

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $authResult = new AuthenticateUserResult(
      authenticated: true,
      userId: null,
      email: 'test@example.com'
    );

    $this->queryBus
      ->expects($this->once())
      ->method('ask')
      ->willReturn($authResult);

    $this->expectException(UnauthorizedHttpException::class);

    $this->processor->process($loginInput, $operation);
  }

  /**
   * Method testProcessThrowsUnauthorizedWhenEmailIsNull
   *
   * Tests that the processor throws UnauthorizedHttpException when email is null.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessThrowsUnauthorizedWhenEmailIsNull(): void
  {
    $loginInput = new LoginInput();
    $loginInput->email = null;
    $loginInput->password = 'password123';

    $operation = $this->createMock(Operation::class);
    $request = Request::create('/api/auth/login', 'POST');

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $this->expectException(UnauthorizedHttpException::class);

    $this->processor->process($loginInput, $operation);
  }

  /**
   * Method testProcessThrowsUnauthorizedWhenPasswordIsNull
   *
   * Tests that the processor throws UnauthorizedHttpException when password is null.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessThrowsUnauthorizedWhenPasswordIsNull(): void
  {
    $loginInput = new LoginInput();
    $loginInput->email = 'test@example.com';
    $loginInput->password = null;

    $operation = $this->createMock(Operation::class);
    $request = Request::create('/api/auth/login', 'POST');

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $this->expectException(UnauthorizedHttpException::class);

    $this->processor->process($loginInput, $operation);
  }

  /**
   * Method testProcessThrowsUnauthorizedWhenUserIdIsEmpty
   *
   * Tests that the processor throws UnauthorizedHttpException when userId is empty string.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessThrowsUnauthorizedWhenUserIdIsEmpty(): void
  {
    $loginInput = new LoginInput();
    $loginInput->email = 'test@example.com';
    $loginInput->password = 'password123';

    $operation = $this->createMock(Operation::class);
    $request = Request::create('/api/auth/login', 'POST');

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $authResult = new AuthenticateUserResult(
      authenticated: true,
      userId: '',
      email: 'test@example.com'
    );

    $this->queryBus
      ->expects($this->once())
      ->method('ask')
      ->willReturn($authResult);

    $this->expectException(UnauthorizedHttpException::class);

    $this->processor->process($loginInput, $operation);
  }

  /**
   * Method testProcessThrowsUnauthorizedOnQueryBusException
   *
   * Tests that the processor throws UnauthorizedHttpException when query bus throws.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessThrowsUnauthorizedOnQueryBusException(): void
  {
    $loginInput = new LoginInput();
    $loginInput->email = 'test@example.com';
    $loginInput->password = 'password123';

    $operation = $this->createMock(Operation::class);
    $request = Request::create('/api/auth/login', 'POST');

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $this->queryBus
      ->expects($this->once())
      ->method('ask')
      ->willThrowException(new \RuntimeException('Database error'));

    $this->expectException(UnauthorizedHttpException::class);

    $this->processor->process($loginInput, $operation);
  }

  /**
   * Method testProcessSetsRefreshTokenCookie
   *
   * Tests that the processor sets the refresh token cookie on request attributes.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testProcessSetsRefreshTokenCookie(): void
  {
    $loginInput = new LoginInput();
    $loginInput->email = 'test@example.com';
    $loginInput->password = 'password123';

    $operation = $this->createMock(Operation::class);
    $request = Request::create('/api/auth/login', 'POST');

    $this->requestStack
      ->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $authResult = new AuthenticateUserResult(
      authenticated: true,
      userId: 'user-123',
      email: 'test@example.com',
      fullName: 'Test User'
    );

    $this->queryBus
      ->expects($this->once())
      ->method('ask')
      ->willReturn($authResult);

    $this->tokenService
      ->expects($this->once())
      ->method('generateTokens')
      ->willReturn([
        'access_token' => 'access_token_value',
        'refresh_token' => 'refresh_token_value',
        'token_type' => 'Bearer',
        'expires_in' => 3600,
      ]);

    $cookie = new \Symfony\Component\HttpFoundation\Cookie('refresh_token', 'refresh_token_value');
    $this->cookieService
      ->expects($this->once())
      ->method('createCookie')
      ->with('refresh_token_value', false)
      ->willReturn($cookie);

    $this->processor->process($loginInput, $operation);

    $this->assertEquals($cookie, $request->attributes->get('_refresh_token_cookie'));
  }
  //#endregion
}
