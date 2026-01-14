<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Processor\Auth;

use ApiPlatform\Metadata\Post;
use Auth\Application\UseCase\Command\Session\Logout\LogoutCommand;
use Auth\Presentation\Api\Dto\Output\Auth\LogoutOutput;
use Auth\Presentation\Api\Processor\Auth\LogoutProcessor;
use Auth\Presentation\Api\Service\RefreshTokenCookieService;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\HttpFoundation\{Request, RequestStack};

/**
 * Test LogoutProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: LogoutProcessor::class)]
final class LogoutProcessorTest extends TestCase
{
  // #region Methods
  /**
   * Method testProcessDispatchesLogoutAndSetsCookie.
   */
  #[Test]
  public function testProcessDispatchesLogoutAndSetsCookie(): void
  {
    $request = new Request();
    $request->headers->set('Authorization', 'Bearer access-token');
    $request->cookies->set('refresh_token', 'refresh-token');

    $requestStack = new RequestStack();
    $requestStack->push($request);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(function (LogoutCommand $command): bool {
        return 'refresh-token' === $command->refreshToken
          && 'access-token' === $command->accessToken;
      }));

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('info')
      ->with('User logged out', self::arrayHasKey('ip'));

    $cookieService = new RefreshTokenCookieService(
      environment: 'test',
      cookieBaseName: 'refresh_token',
      lifetimeShort: 3600,
      lifetimeLong: 7200,
    );

    $processor = new LogoutProcessor(
      requestStack: $requestStack,
      cookieService: $cookieService,
      commandBus: $commandBus,
      logger: $logger,
    );

    $output = $processor->process(null, new Post());

    $this->assertInstanceOf(LogoutOutput::class, $output);

    $cookie = $request->attributes->get('_refresh_token_cookie');
    $this->assertInstanceOf(\Symfony\Component\HttpFoundation\Cookie::class, $cookie);
    $this->assertSame('', $cookie->getValue());
  }
  // #endregion
}
