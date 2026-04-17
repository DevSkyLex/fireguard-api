<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Processor\Auth;

use ApiPlatform\Metadata\Post;
use Auth\Application\Port\Outbound\JwtTokenServicePort;
use Auth\Application\UseCase\Query\Session\RefreshToken\RefreshTokenResult;
use Auth\Presentation\Api\Dto\Output\Auth\LoginOutput;
use Auth\Presentation\Api\Processor\Auth\RefreshTokenProcessor;
use Auth\Presentation\Api\Service\RefreshTokenCookieService;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{TooManyRequestsHttpException, UnauthorizedHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

use function hash;
use function sprintf;
use function substr;

/**
 * Test RefreshTokenProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: RefreshTokenProcessor::class)]
final class RefreshTokenProcessorTest extends TestCase
{
  // #region Methods
  /**
   * Method testProcessReturnsNullWhenNoRequest.
   */
  #[Test]
  public function testProcessReturnsNullWhenNoRequest(): void
  {
    $processor = new RefreshTokenProcessor(
      queryBus: $this->createMock(QueryBusPort::class),
      requestStack: new RequestStack(),
      cookieService: $this->createMock(RefreshTokenCookieService::class),
      jwtService: $this->createMock(JwtTokenServicePort::class),
    );

    $this->assertNull($processor->process(null, new Post()));
  }

  /**
   * Method testProcessThrowsWhenNoRefreshToken.
   */
  #[Test]
  public function testProcessThrowsWhenNoRefreshToken(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $processor = new RefreshTokenProcessor(
      queryBus: $this->createMock(QueryBusPort::class),
      requestStack: $requestStack,
      cookieService: $this->createMock(RefreshTokenCookieService::class),
      jwtService: $this->createMock(JwtTokenServicePort::class),
    );

    $this->expectException(UnauthorizedHttpException::class);
    $processor->process(null, new Post());
  }

  /**
   * Method testProcessThrowsWhenRefreshFails.
   */
  #[Test]
  public function testProcessThrowsWhenRefreshFails(): void
  {
    $request = new Request();
    $request->cookies->set('refresh_token', 'refresh-token');

    $requestStack = new RequestStack();
    $requestStack->push($request);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(RefreshTokenResult::failed('Invalid refresh token'));

    $cookieService = new RefreshTokenCookieService(
      environment: 'test',
      cookieBaseName: 'refresh_token',
      lifetimeShort: 3600,
      lifetimeLong: 7200,
    );

    $processor = new RefreshTokenProcessor(
      queryBus: $queryBus,
      requestStack: $requestStack,
      cookieService: $cookieService,
      jwtService: $this->createMock(JwtTokenServicePort::class),
    );

    try {
      $processor->process(null, new Post());
      $this->fail('Expected UnauthorizedHttpException');
    } catch (UnauthorizedHttpException) {
      $cookie = $request->attributes->get('_refresh_token_cookie');
      $this->assertInstanceOf(\Symfony\Component\HttpFoundation\Cookie::class, $cookie);
      $this->assertSame('', $cookie->getValue());
    }
  }

  /**
   * Method testProcessReturnsOutputAndSetsCookieOnSuccess.
   */
  #[Test]
  public function testProcessReturnsOutputAndSetsCookieOnSuccess(): void
  {
    $request = new Request();
    $request->cookies->set('refresh_token', 'refresh-token');

    $requestStack = new RequestStack();
    $requestStack->push($request);

    $result = new RefreshTokenResult(
      success: true,
      accessToken: 'access',
      refreshToken: 'refresh-new',
      tokenType: 'Bearer',
      expiresIn: 3600,
      scopes: ['READ', 'WRITE'],
      rememberMe: true,
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn($result);

    /** @var JwtTokenServicePort&MockObject $jwtService */
    $jwtService = $this->createMock(JwtTokenServicePort::class);
    $jwtService->expects(self::never())->method('decodeRefreshToken');

    $cookieService = new RefreshTokenCookieService(
      environment: 'test',
      cookieBaseName: 'refresh_token',
      lifetimeShort: 3600,
      lifetimeLong: 7200,
    );

    $processor = new RefreshTokenProcessor(
      queryBus: $queryBus,
      requestStack: $requestStack,
      cookieService: $cookieService,
      jwtService: $jwtService,
    );

    $output = $processor->process(null, new Post());

    $this->assertInstanceOf(LoginOutput::class, $output);
    $this->assertSame('access', $output->accessToken);
    $this->assertSame('Bearer', $output->tokenType);
    $this->assertSame(3600, $output->expiresIn);
    $this->assertSame('READ WRITE', $output->scope);

    $cookie = $request->attributes->get('_refresh_token_cookie');
    $this->assertInstanceOf(\Symfony\Component\HttpFoundation\Cookie::class, $cookie);
    $this->assertSame('refresh-new', $cookie->getValue());
  }

  #[Test]
  public function testProcessThrowsTooManyRequestsWhenRateLimited(): void
  {
    $request = new Request();
    $request->cookies->set('refresh_token', 'refresh-token');
    $request->server->set('REMOTE_ADDR', '127.0.0.1');

    $requestStack = new RequestStack();
    $requestStack->push($request);

    /** @var JwtTokenServicePort&MockObject $jwtService */
    $jwtService = $this->createMock(JwtTokenServicePort::class);
    $jwtService->expects(self::once())
      ->method('decodeRefreshToken')
      ->with('refresh-token')
      ->willReturn(['user_id' => 'user-123']);

    $rateLimiter = $this->createRateLimiterFactory(limit: 1);
    $rateLimiter->create($this->createRateLimitKey('user:user-123', '127.0.0.1'))->consume();

    $cookieService = new RefreshTokenCookieService(
      environment: 'test',
      cookieBaseName: 'refresh_token',
      lifetimeShort: 3600,
      lifetimeLong: 7200,
    );

    $processor = new RefreshTokenProcessor(
      queryBus: $this->createMock(QueryBusPort::class),
      requestStack: $requestStack,
      cookieService: $cookieService,
      jwtService: $jwtService,
      rateLimiter: $rateLimiter,
    );

    $this->expectException(TooManyRequestsHttpException::class);

    $processor->process(null, new Post());
  }

  private function createRateLimiterFactory(int $limit = 10): RateLimiterFactory
  {
    return new RateLimiterFactory(
      config: [
        'id' => 'token_refresh',
        'policy' => 'fixed_window',
        'limit' => $limit,
        'interval' => '1 hour',
      ],
      storage: new InMemoryStorage(),
    );
  }

  private function createRateLimitKey(string $identity, string $ipAddress): string
  {
    return sprintf(
      'token_refresh_%s_%s',
      substr(hash('sha256', $identity), 0, 16),
      substr(hash('sha256', $ipAddress), 0, 16),
    );
  }
  // #endregion
}
