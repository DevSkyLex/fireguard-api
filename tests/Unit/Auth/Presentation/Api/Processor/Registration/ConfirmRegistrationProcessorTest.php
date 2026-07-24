<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Processor\Registration;

use ApiPlatform\Metadata\Post;
use Auth\Application\UseCase\Command\Registration\ConfirmRegistration\{ConfirmRegistrationCommand, ConfirmRegistrationResult};
use Auth\Presentation\Api\Dto\Input\Registration\ConfirmRegistrationInput;
use Auth\Presentation\Api\Dto\Output\Auth\LoginOutput;
use Auth\Presentation\Api\Processor\Registration\ConfirmRegistrationProcessor;
use Auth\Presentation\Api\Service\RefreshTokenCookieService;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

#[CoversClass(ConfirmRegistrationProcessor::class)]
final class ConfirmRegistrationProcessorTest extends TestCase
{
  #[Test]
  public function testProcessReturnsLoginOutputAndStagesCookieOnSuccess(): void
  {
    $request = Request::create(
      uri: '/auth/register/verify',
      method: 'POST',
      server: ['REMOTE_ADDR' => '127.0.0.1'],
    );
    $requestStack = new RequestStack();
    $requestStack->push($request);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (ConfirmRegistrationCommand $command): bool => 'challenge-123' === $command->token
          && '123456' === $command->code
          && '127.0.0.1' === $command->ipAddress,
      ))
      ->willReturn(ConfirmRegistrationResult::success(
        accessToken: 'access-token-abc',
        refreshToken: 'refresh-token-xyz',
        tokenType: 'Bearer',
        expiresIn: 3600,
        scopes: ['OPENID', 'PROFILE'],
      ));

    $processor = new ConfirmRegistrationProcessor(
      commandBus: $commandBus,
      requestStack: $requestStack,
      cookieService: new RefreshTokenCookieService(environment: 'test'),
    );

    $input = new ConfirmRegistrationInput();
    $input->token = 'challenge-123';
    $input->code = '123456';

    $output = $processor->process($input, new Post());

    self::assertInstanceOf(LoginOutput::class, $output);
    self::assertSame('access-token-abc', $output->accessToken);
    self::assertSame('Bearer', $output->tokenType);
    self::assertSame(3600, $output->expiresIn);
    self::assertSame('OPENID PROFILE', $output->scope);

    $cookie = $request->attributes->get('_refresh_token_cookie');
    self::assertInstanceOf(Cookie::class, $cookie);
    self::assertSame('refresh-token-xyz', $cookie->getValue());
  }

  #[Test]
  public function testProcessThrowsUnauthorizedWhenInvalidCode(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/auth/register/verify',
      method: 'POST',
      server: ['REMOTE_ADDR' => '127.0.0.1'],
    ));

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturn(ConfirmRegistrationResult::failed(
        message: 'Invalid verification code.',
        errorCode: ConfirmRegistrationResult::ERROR_INVALID_CODE,
        attemptsRemaining: 4,
      ));

    $processor = new ConfirmRegistrationProcessor(
      commandBus: $commandBus,
      requestStack: $requestStack,
      cookieService: new RefreshTokenCookieService(environment: 'test'),
    );

    $input = new ConfirmRegistrationInput();
    $input->token = 'challenge-123';
    $input->code = '000000';

    $this->expectException(UnauthorizedHttpException::class);

    $processor->process($input, new Post());
  }
}
