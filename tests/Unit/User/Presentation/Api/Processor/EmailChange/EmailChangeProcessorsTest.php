<?php

declare(strict_types=1);

namespace Tests\Unit\User\Presentation\Api\Processor\EmailChange;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, TooManyRequestsHttpException, UnprocessableEntityHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use User\Application\UseCase\Command\EmailChange\CancelEmailChange\{CancelEmailChangeCommand, CancelEmailChangeResult};
use User\Application\UseCase\Command\EmailChange\ConfirmEmailChange\{ConfirmEmailChangeCommand, ConfirmEmailChangeResult};
use User\Application\UseCase\Command\EmailChange\RequestEmailChange\{RequestEmailChangeCommand, RequestEmailChangeResult};
use User\Presentation\Api\Dto\Input\EmailChange\{ConfirmEmailChangeInput, RequestEmailChangeInput};
use User\Presentation\Api\Processor\EmailChange\{CancelEmailChangeProcessor, ConfirmEmailChangeProcessor, RequestEmailChangeProcessor};

/**
 * Test EmailChangeProcessorsTest.
 *
 * HTTP mapping owned by the three email change processors: result
 * error codes to statuses (invalid password to 422, unusable address
 * to a neutral 409, invalid token to a neutral 400), the auth guard,
 * and the command each one dispatches.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RequestEmailChangeProcessor::class)]
#[CoversClass(ConfirmEmailChangeProcessor::class)]
#[CoversClass(CancelEmailChangeProcessor::class)]
final class EmailChangeProcessorsTest extends TestCase
{
  private const string USER_ID = '00000000-0000-4000-a000-000000000001';

  // #region Request tests
  #[Test]
  public function testRequestRefusesUnauthenticatedCaller(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new RequestEmailChangeProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
      requestStack: new RequestStack(),
    );

    $this->expectException(AccessDeniedHttpException::class);
    $processor->process($this->requestInput(), new Post());
  }

  #[Test]
  public function testRequestMapsInvalidPasswordTo422(): void
  {
    $this->expectException(UnprocessableEntityHttpException::class);

    $this->requestProcessor(RequestEmailChangeResult::failed(
      message: 'The current password is incorrect.',
      errorCode: RequestEmailChangeResult::ERROR_INVALID_PASSWORD,
    ))->process($this->requestInput(), new Post());
  }

  #[Test]
  public function testRequestMapsUnusableAddressToNeutral409(): void
  {
    try {
      $this->requestProcessor(RequestEmailChangeResult::failed(
        message: 'This email address cannot be used.',
        errorCode: RequestEmailChangeResult::ERROR_EMAIL_NOT_AVAILABLE,
      ))->process($this->requestInput(), new Post());
      self::fail('A ConflictHttpException should have been thrown.');
    } catch (ConflictHttpException $exception) {
      self::assertSame('This email address cannot be used.', $exception->getMessage());
    }
  }

  #[Test]
  public function testRequestDispatchesCommandForAuthenticatedUserAndReturnsOutput(): void
  {
    $expiresAt = new DateTimeImmutable('+1 hour');
    $dispatched = null;

    /** @var CommandBusPort&MockObject $bus */
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')
      ->willReturnCallback(static function (CommandMessage $command) use (&$dispatched, $expiresAt): ResultMessage {
        $dispatched = $command;

        return RequestEmailChangeResult::success(expiresAt: $expiresAt);
      });

    $output = $this->requestProcessor(bus: $bus)->process($this->requestInput(), new Post());

    self::assertInstanceOf(RequestEmailChangeCommand::class, $dispatched);
    self::assertSame(self::USER_ID, $dispatched->userId);
    self::assertSame('new-address@example.com', $dispatched->newEmail);
    self::assertSame('CurrentP@ssw0rd!', $dispatched->currentPassword);
    self::assertTrue($output->success);
    self::assertSame($expiresAt, $output->expiresAt);
  }

  #[Test]
  public function testRequestBeyondThePerUserBudgetIs429(): void
  {
    // Per-user dimension: the second call of a 1/min budget is refused
    // even though the IP budget is still open.
    $processor = new RequestEmailChangeProcessor(
      commandBus: $this->successBus(),
      security: $this->authenticatedSecurity(),
      requestStack: $this->requestStackWithClientIp('203.0.113.9'),
      rateLimiter: $this->limiterFactory(limit: 1),
      ipRateLimiter: $this->limiterFactory(limit: 100),
    );

    $processor->process($this->requestInput(), new Post());

    $this->expectException(TooManyRequestsHttpException::class);
    $processor->process($this->requestInput(), new Post());
  }

  #[Test]
  public function testRequestBeyondThePerIpBudgetIs429EvenAcrossUsers(): void
  {
    // Per-IP dimension: the user budget stays open (fresh key per call
    // would not even matter at limit 100), yet the shared IP bucket
    // refuses the second call — this is what stops an attacker from
    // scaling the per-user budget horizontally with many accounts.
    $ipLimiter = $this->limiterFactory(limit: 1);

    $first = new RequestEmailChangeProcessor(
      commandBus: $this->successBus(),
      security: $this->authenticatedSecurity(),
      requestStack: $this->requestStackWithClientIp('203.0.113.9'),
      rateLimiter: $this->limiterFactory(limit: 100),
      ipRateLimiter: $ipLimiter,
    );
    $first->process($this->requestInput(), new Post());

    $second = new RequestEmailChangeProcessor(
      commandBus: $this->successBus(),
      security: $this->authenticatedSecurity(id: '00000000-0000-4000-a000-000000000002'),
      requestStack: $this->requestStackWithClientIp('203.0.113.9'),
      rateLimiter: $this->limiterFactory(limit: 100),
      ipRateLimiter: $ipLimiter,
    );

    $this->expectException(TooManyRequestsHttpException::class);
    $second->process($this->requestInput(), new Post());
  }
  // #endregion

  // #region Confirm tests
  #[Test]
  public function testConfirmMapsInvalidTokenToNeutral400(): void
  {
    $bus = $this->createStub(CommandBusPort::class);
    $bus->method('dispatch')->willReturn(ConfirmEmailChangeResult::failed(
      message: 'Invalid or expired email change token.',
      errorCode: ConfirmEmailChangeResult::ERROR_INVALID_TOKEN,
    ));

    $processor = new ConfirmEmailChangeProcessor(
      commandBus: $bus,
      requestStack: new RequestStack(),
    );

    $input = new ConfirmEmailChangeInput();
    $input->token = 'whatever';

    try {
      $processor->process($input, new Post());
      self::fail('A BadRequestHttpException should have been thrown.');
    } catch (BadRequestHttpException $exception) {
      self::assertSame('Invalid or expired email change token.', $exception->getMessage());
    }
  }

  #[Test]
  public function testConfirmMapsRacedAddressToNeutral409(): void
  {
    $bus = $this->createStub(CommandBusPort::class);
    $bus->method('dispatch')->willReturn(ConfirmEmailChangeResult::failed(
      message: 'This email address cannot be used.',
      errorCode: ConfirmEmailChangeResult::ERROR_EMAIL_NOT_AVAILABLE,
    ));

    $processor = new ConfirmEmailChangeProcessor(
      commandBus: $bus,
      requestStack: new RequestStack(),
    );

    $input = new ConfirmEmailChangeInput();
    $input->token = 'whatever';

    $this->expectException(ConflictHttpException::class);
    $processor->process($input, new Post());
  }

  #[Test]
  public function testConfirmDispatchesTokenAndReturnsOutput(): void
  {
    $dispatched = null;

    /** @var CommandBusPort&MockObject $bus */
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')
      ->willReturnCallback(static function (CommandMessage $command) use (&$dispatched): ResultMessage {
        $dispatched = $command;

        return ConfirmEmailChangeResult::success();
      });

    $processor = new ConfirmEmailChangeProcessor(
      commandBus: $bus,
      requestStack: new RequestStack(),
    );

    $input = new ConfirmEmailChangeInput();
    $input->token = 'raw-token-from-email';

    $output = $processor->process($input, new Post());

    self::assertInstanceOf(ConfirmEmailChangeCommand::class, $dispatched);
    self::assertSame('raw-token-from-email', $dispatched->token);
    self::assertTrue($output->success);
  }
  // #endregion

  // #region Cancel tests
  #[Test]
  public function testCancelRefusesUnauthenticatedCaller(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new CancelEmailChangeProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $processor->process(null, new Post());
  }

  #[Test]
  public function testCancelDispatchesCommandForAuthenticatedUser(): void
  {
    $dispatched = null;

    /** @var CommandBusPort&MockObject $bus */
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')
      ->willReturnCallback(static function (CommandMessage $command) use (&$dispatched): ResultMessage {
        $dispatched = $command;

        return new CancelEmailChangeResult(cancelled: true);
      });

    $processor = new CancelEmailChangeProcessor(
      commandBus: $bus,
      security: $this->authenticatedSecurity(),
    );

    $processor->process(null, new Post());

    self::assertInstanceOf(CancelEmailChangeCommand::class, $dispatched);
    self::assertSame(self::USER_ID, $dispatched->userId);
  }
  // #endregion

  // #region Helpers
  private function requestInput(): RequestEmailChangeInput
  {
    $input = new RequestEmailChangeInput();
    $input->newEmail = 'new-address@example.com';
    $input->currentPassword = 'CurrentP@ssw0rd!';

    return $input;
  }

  private function requestProcessor(?ResultMessage $result = null, ?CommandBusPort $bus = null): RequestEmailChangeProcessor
  {
    if (null === $bus) {
      $bus = $this->createStub(CommandBusPort::class);
      $bus->method('dispatch')->willReturn(
        $result ?? RequestEmailChangeResult::success(expiresAt: new DateTimeImmutable('+1 hour')),
      );
    }

    return new RequestEmailChangeProcessor(
      commandBus: $bus,
      security: $this->authenticatedSecurity(),
      requestStack: new RequestStack(),
    );
  }

  private function successBus(): CommandBusPort
  {
    $bus = $this->createStub(CommandBusPort::class);
    $bus->method('dispatch')->willReturn(
      RequestEmailChangeResult::success(expiresAt: new DateTimeImmutable('+1 hour')),
    );

    return $bus;
  }

  private function requestStackWithClientIp(string $clientIp): RequestStack
  {
    $stack = new RequestStack();
    $stack->push(Request::create(uri: '/api/me/email-change', method: 'POST', server: ['REMOTE_ADDR' => $clientIp]));

    return $stack;
  }

  private function limiterFactory(int $limit): RateLimiterFactory
  {
    return new RateLimiterFactory(
      config: [
        'id' => 'email_change_test',
        'policy' => 'sliding_window',
        'limit' => $limit,
        'interval' => '1 minute',
      ],
      storage: new InMemoryStorage(),
    );
  }

  private function authenticatedSecurity(string $id = self::USER_ID): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: $id,
      email: 'jdoe@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }
  // #endregion
}
