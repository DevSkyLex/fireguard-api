<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Processor\PasswordReset;

use ApiPlatform\Metadata\Post;
use Auth\Application\UseCase\Command\PasswordReset\RequestPasswordReset\{RequestPasswordResetCommand, RequestPasswordResetResult};
use Auth\Presentation\Api\Dto\Input\PasswordReset\RequestPasswordResetInput;
use Auth\Presentation\Api\Dto\Output\PasswordReset\RequestPasswordResetOutput;
use Auth\Presentation\Api\Processor\PasswordReset\RequestPasswordResetProcessor;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

use function hash;
use function sprintf;
use function substr;

#[CoversClass(RequestPasswordResetProcessor::class)]
final class RequestPasswordResetProcessorTest extends TestCase
{
  #[Test]
  public function testProcessReturnsOutputOnSuccess(): void
  {
    $expiresAt = new DateTimeImmutable('+15 minutes');
    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/auth/password/reset/request',
      method: 'POST',
      server: ['REMOTE_ADDR' => '127.0.0.1'],
    ));

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (RequestPasswordResetCommand $command): bool => 'user@example.com' === $command->email
          && '127.0.0.1' === $command->ipAddress,
      ))
      ->willReturn(RequestPasswordResetResult::success(
        challengeToken: 'challenge-123',
        maskedRecipient: 'u***@example.com',
        expiresAt: $expiresAt,
        maxAttempts: 5,
        canResendIn: 60,
      ));

    $processor = new RequestPasswordResetProcessor(
      commandBus: $commandBus,
      requestStack: $requestStack,
    );

    $input = new RequestPasswordResetInput();
    $input->email = 'user@example.com';

    $output = $processor->process($input, new Post());

    self::assertInstanceOf(RequestPasswordResetOutput::class, $output);
    self::assertTrue($output->success);
    self::assertSame('challenge-123', $output->challengeToken);
    self::assertSame('u***@example.com', $output->maskedRecipient);
    self::assertSame($expiresAt, $output->expiresAt);
    self::assertSame(5, $output->maxAttempts);
    self::assertSame(60, $output->canResendIn);
  }

  #[Test]
  public function testProcessThrowsTooManyRequestsWhenRateLimited(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      uri: '/auth/password/reset/request',
      method: 'POST',
      server: ['REMOTE_ADDR' => '127.0.0.1'],
    ));

    $rateLimiter = $this->createRateLimiterFactory(limit: 1);
    $rateLimiter->create($this->createRateLimitKey('127.0.0.1'))->consume();

    $processor = new RequestPasswordResetProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      requestStack: $requestStack,
      rateLimiter: $rateLimiter,
    );

    $input = new RequestPasswordResetInput();
    $input->email = 'user@example.com';

    $this->expectException(TooManyRequestsHttpException::class);

    $processor->process($input, new Post());
  }

  private function createRateLimiterFactory(int $limit = 10): RateLimiterFactory
  {
    return new RateLimiterFactory(
      config: [
        'id' => 'password_reset_request',
        'policy' => 'fixed_window',
        'limit' => $limit,
        'interval' => '1 hour',
      ],
      storage: new InMemoryStorage(),
    );
  }

  private function createRateLimitKey(string $ipAddress): string
  {
    return sprintf('password_reset_request_%s', substr(hash('sha256', $ipAddress), 0, 16));
  }
}
