<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Processor\Auth;

use ApiPlatform\Metadata\Post;
use Auth\Application\UseCase\Query\Session\RefreshToken\RefreshTokenResult;
use Auth\Presentation\Api\Dto\Output\Auth\LoginOutput;
use Auth\Presentation\Api\Processor\Auth\RefreshTokenProcessor;
use Auth\Presentation\Api\Service\RefreshTokenCookieService;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

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
    );

    $this->expectException(UnauthorizedHttpException::class);
    $processor->process(null, new Post());
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
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn($result);

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
  // #endregion
}
