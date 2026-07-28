<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Processor\PasswordChange;

use ApiPlatform\Metadata\Post;
use Auth\Application\UseCase\Command\PasswordChange\RequestPasswordChange\{
  RequestPasswordChangeCommand,
  RequestPasswordChangeResult
};
use Auth\Infrastructure\Security\User\SecurityUser;
use Auth\Presentation\Api\Dto\Input\PasswordChange\RequestPasswordChangeInput;
use Auth\Presentation\Api\Processor\PasswordChange\RequestPasswordChangeProcessor;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  TooManyRequestsHttpException,
  UnprocessableEntityHttpException
};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

use function hash;
use function sprintf;
use function substr;

/**
 * Test RequestPasswordChangeProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RequestPasswordChangeProcessor::class)]
final class RequestPasswordChangeProcessorTest extends TestCase
{
  // #region Constants
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string IP = '203.0.113.10';
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessRejectsAnInvalidInput(): void
  {
    $this->expectException(InvalidArgumentException::class);

    $this->createProcessor($this->createStub(CommandBusPort::class))->process(null, new Post());
  }

  #[Test]
  public function testProcessDispatchesTheCommandAndReturnsTheChallenge(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (RequestPasswordChangeCommand $command): bool => self::USER_ID === $command->userId
        && 'Current123!' === $command->currentPassword
        && self::IP === $command->ipAddress))
      ->willReturn($this->successResult());

    $input = new RequestPasswordChangeInput();
    $input->currentPassword = 'Current123!';

    $output = $this->createProcessor($commandBus)->process($input, new Post());

    self::assertTrue($output->success);
    self::assertSame('challenge-token', $output->challengeToken);
    self::assertSame('u***@example.com', $output->maskedRecipient);
    self::assertSame(5, $output->maxAttempts);
  }

  #[Test]
  public function testProcessDefaultsAMissingCurrentPasswordToAnEmptyString(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (RequestPasswordChangeCommand $command): bool => '' === $command->currentPassword))
      ->willReturn($this->successResult());

    $this->createProcessor($commandBus)->process(new RequestPasswordChangeInput(), new Post());
  }

  #[Test]
  public function testProcessThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new RequestPasswordChangeProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
      requestStack: $this->requestStack(),
      rateLimiter: $this->createRateLimiterFactory(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new RequestPasswordChangeInput(), new Post());
  }

  #[Test]
  public function testProcessThrowsUnprocessableWhenTheCommandFails(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn(RequestPasswordChangeResult::failed(
      'Current password is incorrect.',
      RequestPasswordChangeResult::ERROR_INVALID_PASSWORD,
    ));

    $this->expectException(UnprocessableEntityHttpException::class);
    $this->expectExceptionMessage('Current password is incorrect.');

    $this->createProcessor($commandBus)->process(new RequestPasswordChangeInput(), new Post());
  }

  #[Test]
  public function testProcessThrowsTooManyRequestsWhenTheRateLimitIsExhausted(): void
  {
    $rateLimiter = $this->createRateLimiterFactory(limit: 1);
    $rateLimiter->create($this->rateLimitKey())->consume();

    $processor = $this->createProcessor($this->createStub(CommandBusPort::class), $rateLimiter);

    $this->expectException(TooManyRequestsHttpException::class);

    $processor->process(new RequestPasswordChangeInput(), new Post());
  }

  #[Test]
  public function testProcessSkipsRateLimitingWhenNoLimiterIsWired(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())->method('dispatch')->willReturn($this->successResult());

    $processor = new RequestPasswordChangeProcessor(
      commandBus: $commandBus,
      security: $this->securityWithUser(),
      requestStack: $this->requestStack(),
      rateLimiter: null,
    );

    $output = $processor->process(new RequestPasswordChangeInput(), new Post());

    self::assertTrue($output->success);
  }

  private function createProcessor(
    CommandBusPort $commandBus,
    ?RateLimiterFactory $rateLimiter = null,
  ): RequestPasswordChangeProcessor {
    return new RequestPasswordChangeProcessor(
      commandBus: $commandBus,
      security: $this->securityWithUser(),
      requestStack: $this->requestStack(),
      rateLimiter: $rateLimiter ?? $this->createRateLimiterFactory(),
    );
  }

  private function requestStack(): RequestStack
  {
    $stack = new RequestStack();
    $stack->push(new Request(server: ['REMOTE_ADDR' => self::IP]));

    return $stack;
  }

  private function securityWithUser(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }

  private function createRateLimiterFactory(int $limit = 10): RateLimiterFactory
  {
    return new RateLimiterFactory(
      config: [
        'id' => 'password_change_request',
        'policy' => 'fixed_window',
        'limit' => $limit,
        'interval' => '1 hour',
      ],
      storage: new InMemoryStorage(),
    );
  }

  private function rateLimitKey(): string
  {
    return sprintf('password_change_request_%s', substr(hash('sha256', self::USER_ID), 0, 16));
  }

  private function successResult(): RequestPasswordChangeResult
  {
    return new RequestPasswordChangeResult(
      success: true,
      message: 'A verification code has been sent to your email address.',
      errorCode: null,
      challengeToken: 'challenge-token',
      maskedRecipient: 'u***@example.com',
      expiresAt: new DateTimeImmutable('+10 minutes'),
      maxAttempts: 5,
    );
  }
  // #endregion
}
