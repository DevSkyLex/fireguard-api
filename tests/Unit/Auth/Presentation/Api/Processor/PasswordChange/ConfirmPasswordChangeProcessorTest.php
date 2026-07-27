<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Processor\PasswordChange;

use ApiPlatform\Metadata\Post;
use Auth\Application\UseCase\Command\PasswordChange\ConfirmPasswordChange\{
  ConfirmPasswordChangeCommand,
  ConfirmPasswordChangeResult
};
use Auth\Infrastructure\Security\User\SecurityUser;
use Auth\Presentation\Api\Dto\Input\PasswordChange\ConfirmPasswordChangeInput;
use Auth\Presentation\Api\Processor\PasswordChange\ConfirmPasswordChangeProcessor;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  TooManyRequestsHttpException,
  UnauthorizedHttpException
};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

use function hash;
use function sprintf;
use function substr;

/**
 * Test ConfirmPasswordChangeProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ConfirmPasswordChangeProcessor::class)]
final class ConfirmPasswordChangeProcessorTest extends TestCase
{
  // #region Constants
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string TOKEN = 'change-token';

  private const string IP = '203.0.113.10';
  // #endregion

  // #region Methods
  /**
   * @return iterable<string, array{string}>
   */
  public static function unauthorizedErrorCodeProvider(): iterable
  {
    yield 'expired' => [ConfirmPasswordChangeResult::ERROR_EXPIRED];
    yield 'invalid token' => [ConfirmPasswordChangeResult::ERROR_INVALID_TOKEN];
    yield 'invalid code' => [ConfirmPasswordChangeResult::ERROR_INVALID_CODE];
  }

  #[Test]
  public function testProcessRejectsAnInvalidInput(): void
  {
    $this->expectException(InvalidArgumentException::class);

    $this->createProcessor($this->createStub(CommandBusPort::class))->process(null, new Post());
  }

  #[Test]
  public function testProcessDispatchesTheCommandAndReturnsTheSuccessOutput(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (ConfirmPasswordChangeCommand $command): bool => self::USER_ID === $command->userId
        && self::TOKEN === $command->token
        && '123456' === $command->code
        && 'NewPassword123!' === $command->newPassword
        && self::IP === $command->ipAddress))
      ->willReturn(ConfirmPasswordChangeResult::success());

    $output = $this->createProcessor($commandBus)->process($this->input(), new Post());

    self::assertTrue($output->success);
    self::assertNull($output->errorCode);
  }

  #[Test]
  public function testProcessDefaultsMissingFieldsToEmptyStrings(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (ConfirmPasswordChangeCommand $command): bool => '' === $command->token
        && '' === $command->code
        && '' === $command->newPassword))
      ->willReturn(ConfirmPasswordChangeResult::success());

    $this->createProcessor($commandBus)->process(new ConfirmPasswordChangeInput(), new Post());
  }

  #[Test]
  public function testProcessThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new ConfirmPasswordChangeProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
      requestStack: $this->requestStack(),
      rateLimiter: $this->createRateLimiterFactory(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process($this->input(), new Post());
  }

  #[Test]
  public function testProcessMapsMaxAttemptsToHttp429(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn(new ConfirmPasswordChangeResult(
      success: false,
      message: 'Maximum verification attempts exceeded.',
      errorCode: ConfirmPasswordChangeResult::ERROR_MAX_ATTEMPTS,
    ));

    $this->expectException(TooManyRequestsHttpException::class);

    $this->createProcessor($commandBus)->process($this->input(), new Post());
  }

  #[Test]
  #[DataProvider('unauthorizedErrorCodeProvider')]
  public function testProcessMapsTokenAndCodeFailuresToHttp401(string $errorCode): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn(new ConfirmPasswordChangeResult(
      success: false,
      message: 'Invalid or expired token/code.',
      errorCode: $errorCode,
    ));

    $this->expectException(UnauthorizedHttpException::class);

    $this->createProcessor($commandBus)->process($this->input(), new Post());
  }

  #[Test]
  public function testProcessMapsAnyOtherFailureToHttp400(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn(new ConfirmPasswordChangeResult(
      success: false,
      message: 'The new password must differ from the current one.',
      errorCode: ConfirmPasswordChangeResult::ERROR_SAME_PASSWORD,
    ));

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('The new password must differ from the current one.');

    $this->createProcessor($commandBus)->process($this->input(), new Post());
  }

  #[Test]
  public function testProcessThrowsTooManyRequestsWhenTheRateLimitIsExhausted(): void
  {
    $rateLimiter = $this->createRateLimiterFactory(limit: 1);
    $rateLimiter->create($this->rateLimitKey())->consume();

    $processor = $this->createProcessor($this->createStub(CommandBusPort::class), $rateLimiter);

    $this->expectException(TooManyRequestsHttpException::class);

    $processor->process($this->input(), new Post());
  }

  #[Test]
  public function testProcessSkipsRateLimitingWhenNoLimiterIsWired(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturn(ConfirmPasswordChangeResult::success());

    $processor = new ConfirmPasswordChangeProcessor(
      commandBus: $commandBus,
      security: $this->securityWithUser(),
      requestStack: $this->requestStack(),
      rateLimiter: null,
    );

    self::assertTrue($processor->process($this->input(), new Post())->success);
  }

  private function input(): ConfirmPasswordChangeInput
  {
    $input = new ConfirmPasswordChangeInput();
    $input->token = self::TOKEN;
    $input->code = '123456';
    $input->newPassword = 'NewPassword123!';

    return $input;
  }

  private function createProcessor(
    CommandBusPort $commandBus,
    ?RateLimiterFactory $rateLimiter = null,
  ): ConfirmPasswordChangeProcessor {
    return new ConfirmPasswordChangeProcessor(
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
        'id' => 'password_change_confirm',
        'policy' => 'fixed_window',
        'limit' => $limit,
        'interval' => '1 hour',
      ],
      storage: new InMemoryStorage(),
    );
  }

  private function rateLimitKey(): string
  {
    return sprintf(
      'password_change_confirm_%s_%s',
      substr(hash('sha256', self::USER_ID), 0, 16),
      substr(hash('sha256', self::TOKEN), 0, 16),
    );
  }
  // #endregion
}
