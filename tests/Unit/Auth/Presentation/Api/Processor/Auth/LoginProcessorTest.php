<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Processor\Auth;

use ApiPlatform\Metadata\Post;
use Auth\Application\UseCase\Command\Session\Login\LoginResult;
use Auth\Presentation\Api\Dto\Input\Auth\LoginInput;
use Auth\Presentation\Api\Dto\Output\Auth\LoginOutput;
use Auth\Presentation\Api\Processor\Auth\LoginProcessor;
use Auth\Presentation\Api\Service\RefreshTokenCookieService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use stdClass;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{TooManyRequestsHttpException, UnauthorizedHttpException};

/**
 * Test LoginProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: LoginProcessor::class)]
final class LoginProcessorTest extends TestCase
{
  // #region Methods
  /**
   * Method testProcessThrowsOnInvalidInput.
   */
  #[Test]
  public function testProcessThrowsOnInvalidInput(): void
  {
    $processor = new LoginProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      requestStack: new RequestStack(),
      cookieService: $this->createMock(RefreshTokenCookieService::class),
    );

    $this->expectException(InvalidArgumentException::class);
    // @phpstan-ignore-next-line Intentional invalid type for testing
    $processor->process(new stdClass(), new Post());
  }

  /**
   * Method testProcessThrowsUnauthorizedWhenFailed.
   */
  #[Test]
  public function testProcessThrowsUnauthorizedWhenFailed(): void
  {
    $input = new LoginInput();
    $input->email = 'user@example.com';
    $input->password = 'secret';

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturn(LoginResult::failed('Invalid credentials'));

    $processor = new LoginProcessor(
      commandBus: $commandBus,
      requestStack: new RequestStack(),
      cookieService: $this->createMock(RefreshTokenCookieService::class),
    );

    $this->expectException(UnauthorizedHttpException::class);
    $processor->process($input, new Post());
  }

  /**
   * Method testProcessThrowsTooManyRequestsWhenRateLimited.
   */
  #[Test]
  public function testProcessThrowsTooManyRequestsWhenRateLimited(): void
  {
    $input = new LoginInput();
    $input->email = 'user@example.com';
    $input->password = 'secret';

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturn(LoginResult::failed(
        'Too many login attempts. Please try again in 30 seconds.',
        LoginResult::ERROR_RATE_LIMIT,
        30,
      ));

    $processor = new LoginProcessor(
      commandBus: $commandBus,
      requestStack: new RequestStack(),
      cookieService: $this->createMock(RefreshTokenCookieService::class),
    );

    $this->expectException(TooManyRequestsHttpException::class);
    $processor->process($input, new Post());
  }

  /**
   * Method testProcessReturnsMfaOutputWhenRequired.
   */
  #[Test]
  public function testProcessReturnsMfaOutputWhenRequired(): void
  {
    $input = new LoginInput();
    $input->email = 'user@example.com';
    $input->password = 'secret';

    $result = new LoginResult(
      authenticated: true,
      userId: 'user-123',
      email: 'user@example.com',
      mfaRequired: true,
      mfaToken: 'pre-auth',
      challengeToken: 'challenge',
    );

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturn($result);

    $processor = new LoginProcessor(
      commandBus: $commandBus,
      requestStack: new RequestStack(),
      cookieService: $this->createMock(RefreshTokenCookieService::class),
    );

    $output = $processor->process($input, new Post());

    $this->assertInstanceOf(LoginOutput::class, $output);
    $this->assertTrue($output->mfaRequired);
    $this->assertSame('pre-auth', $output->mfaToken);
    $this->assertSame('challenge', $output->challengeToken);
  }

  /**
   * Method testProcessSetsRefreshTokenCookieOnSuccess.
   */
  #[Test]
  public function testProcessSetsRefreshTokenCookieOnSuccess(): void
  {
    $input = new LoginInput();
    $input->email = 'user@example.com';
    $input->password = 'secret';
    $input->rememberMe = true;

    $result = new LoginResult(
      authenticated: true,
      userId: 'user-123',
      email: 'user@example.com',
      accessToken: 'access',
      refreshToken: 'refresh',
      tokenType: 'Bearer',
      expiresIn: 3600,
      scopes: ['READ', 'WRITE'],
    );

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturn($result);

    $request = new Request();
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $cookieService = new RefreshTokenCookieService(
      environment: 'test',
      cookieBaseName: 'refresh_token',
      lifetimeShort: 3600,
      lifetimeLong: 7200,
    );

    $processor = new LoginProcessor(
      commandBus: $commandBus,
      requestStack: $requestStack,
      cookieService: $cookieService,
    );

    $output = $processor->process($input, new Post());

    $this->assertSame('access', $output->accessToken);
    $this->assertSame('Bearer', $output->tokenType);
    $this->assertSame(3600, $output->expiresIn);
    $this->assertSame('READ WRITE', $output->scope);

    $cookie = $request->attributes->get('_refresh_token_cookie');
    $this->assertInstanceOf(\Symfony\Component\HttpFoundation\Cookie::class, $cookie);
    $this->assertSame('refresh', $cookie->getValue());
  }
  // #endregion
}
