<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Organization\ResendOrganizationInvitation\{
  ResendOrganizationInvitationCommand,
  ResendOrganizationInvitationResult
};
use Organization\Domain\Exception\{
  OrganizationInvitationNotFoundException,
  OrganizationInvitationNotificationFailedException
};
use Organization\Presentation\Api\Processor\Organization\ResendOrganizationInvitationProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  HttpException,
  NotFoundHttpException,
  TooManyRequestsHttpException
};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Throwable;

/**
 * Test ResendOrganizationInvitationProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ResendOrganizationInvitationProcessor::class)]
final class ResendOrganizationInvitationProcessorTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string INVITATION_ID = '550e8400-e29b-41d4-a716-446655440040';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const int HTTP_BAD_GATEWAY = 502;
  // #endregion

  // #region Methods
  /**
   * @return iterable<string, array{array<string, mixed>}>
   */
  public static function incompleteUriVariablesProvider(): iterable
  {
    yield 'no variables' => [[]];
    yield 'blank organizationId' => [['organizationId' => '', 'invitationId' => self::INVITATION_ID]];
    yield 'missing invitationId' => [['organizationId' => self::ORGANIZATION_ID]];
    yield 'blank invitationId' => [['organizationId' => self::ORGANIZATION_ID, 'invitationId' => '']];
  }

  #[Test]
  public function testProcessResendsTheInvitationAndMapsTheOutput(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (ResendOrganizationInvitationCommand $command): bool => self::ORGANIZATION_ID === $command->organizationId
        && self::INVITATION_ID === $command->invitationId
        && self::USER_ID === $command->resentByUserId))
      ->willReturn($this->invitationResult());

    $output = $this->createProcessor($commandBus)->process(null, new Post(), $this->uriVariables());

    self::assertSame(self::INVITATION_ID, $output->id);
    self::assertSame('pending', $output->status);
    self::assertSame('invitee@example.com', $output->email);
    self::assertSame(['role-1'], $output->roleIds);
    self::assertSame('https://app.fireguard.test/invitations/token', $output->acceptUrl);
  }

  #[Test]
  public function testProcessThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new ResendOrganizationInvitationProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->authorization(true),
      security: $security,
      rateLimiter: $this->createRateLimiterFactory(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Post(), $this->uriVariables());
  }

  /**
   * @param array<string, mixed> $uriVariables
   */
  #[Test]
  #[DataProvider('incompleteUriVariablesProvider')]
  public function testProcessThrowsWhenUriVariablesAreIncomplete(array $uriVariables): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor()->process(null, new Post(), $uriVariables);
  }

  #[Test]
  public function testProcessThrowsWhenThePermissionIsMissing(): void
  {
    $processor = new ResendOrganizationInvitationProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->authorization(false),
      security: $this->securityWithUser(),
      rateLimiter: $this->createRateLimiterFactory(),
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing Organization.members.manage permission.');

    $processor->process(null, new Post(), $this->uriVariables());
  }

  #[Test]
  public function testProcessThrowsTooManyRequestsWhenTheRateLimitIsExhausted(): void
  {
    $rateLimiter = $this->createRateLimiterFactory(limit: 1);
    $rateLimiter->create(self::USER_ID)->consume();

    $processor = $this->createProcessor(rateLimiter: $rateLimiter);

    $this->expectException(TooManyRequestsHttpException::class);

    $processor->process(null, new Post(), $this->uriVariables());
  }

  #[Test]
  public function testProcessSkipsRateLimitingWhenNoLimiterIsWired(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())->method('dispatch')->willReturn($this->invitationResult());

    $processor = new ResendOrganizationInvitationProcessor(
      commandBus: $commandBus,
      authorization: $this->authorization(true),
      security: $this->securityWithUser(),
      rateLimiter: null,
    );

    self::assertSame(
      self::INVITATION_ID,
      $processor->process(null, new Post(), $this->uriVariables())->id,
    );
  }

  #[Test]
  public function testProcessMapsAMissingInvitationToHttp404(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      OrganizationInvitationNotFoundException::withId(self::INVITATION_ID),
    );

    $this->expectException(NotFoundHttpException::class);

    $this->createProcessor($commandBus)->process(null, new Post(), $this->uriVariables());
  }

  #[Test]
  public function testProcessMapsAFailedNotificationToHttp502(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      OrganizationInvitationNotificationFailedException::withId(self::INVITATION_ID),
    );

    $this->expectException(HttpException::class);
    $this->expectExceptionCode(0);

    try {
      $this->createProcessor($commandBus)->process(null, new Post(), $this->uriVariables());
    } catch (HttpException $exception) {
      self::assertSame(self::HTTP_BAD_GATEWAY, $exception->getStatusCode());

      throw $exception;
    }
  }

  #[Test]
  public function testProcessMapsAnInvalidArgumentToHttp400(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      new InvalidArgumentException('The invitation was already accepted.'),
    );

    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor($commandBus)->process(null, new Post(), $this->uriVariables());
  }

  #[Test]
  public function testProcessUnwrapsAMissingInvitationWrappedByTheMessengerBus(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->createProcessor($this->throwingBus(
      OrganizationInvitationNotFoundException::withId(self::INVITATION_ID),
    ))->process(null, new Post(), $this->uriVariables());
  }

  #[Test]
  public function testProcessUnwrapsAFailedNotificationWrappedByTheMessengerBus(): void
  {
    $this->expectException(HttpException::class);

    $this->createProcessor($this->throwingBus(
      OrganizationInvitationNotificationFailedException::withId(self::INVITATION_ID),
    ))->process(null, new Post(), $this->uriVariables());
  }

  #[Test]
  public function testProcessUnwrapsAnInvalidArgumentWrappedByTheMessengerBus(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor($this->throwingBus(
      new InvalidArgumentException('The invitation was already accepted.'),
    ))->process(null, new Post(), $this->uriVariables());
  }

  #[Test]
  public function testProcessRethrowsAnUnrecognisedMessengerFailure(): void
  {
    $this->expectException(MessengerRuntimeException::class);

    $this->createProcessor($this->throwingBus(new RuntimeException('database is down')))
      ->process(null, new Post(), $this->uriVariables());
  }

  private function throwingBus(Throwable $domainFailure): CommandBusPort
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(MessengerRuntimeException::wrap(
      new HandlerFailedException(
        new Envelope(new ResendOrganizationInvitationCommand(
          organizationId: self::ORGANIZATION_ID,
          invitationId: self::INVITATION_ID,
          resentByUserId: self::USER_ID,
        )),
        [$domainFailure],
      ),
    ));

    return $commandBus;
  }

  /**
   * @return array<string, string>
   */
  private function uriVariables(): array
  {
    return ['organizationId' => self::ORGANIZATION_ID, 'invitationId' => self::INVITATION_ID];
  }

  private function createProcessor(
    ?CommandBusPort $commandBus = null,
    ?RateLimiterFactory $rateLimiter = null,
  ): ResendOrganizationInvitationProcessor {
    return new ResendOrganizationInvitationProcessor(
      commandBus: $commandBus ?? $this->createStub(CommandBusPort::class),
      authorization: $this->authorization(true),
      security: $this->securityWithUser(),
      rateLimiter: $rateLimiter ?? $this->createRateLimiterFactory(),
    );
  }

  private function authorization(bool $granted): OrganizationAuthorizationPort
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn($granted);

    return $authorization;
  }

  private function createRateLimiterFactory(int $limit = 10): RateLimiterFactory
  {
    return new RateLimiterFactory(
      config: [
        'id' => 'invitation_resend',
        'policy' => 'fixed_window',
        'limit' => $limit,
        'interval' => '1 hour',
      ],
      storage: new InMemoryStorage(),
    );
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

  private function invitationResult(): ResendOrganizationInvitationResult
  {
    return new ResendOrganizationInvitationResult(
      invitationId: self::INVITATION_ID,
      organizationId: self::ORGANIZATION_ID,
      email: 'invitee@example.com',
      status: 'pending',
      invitedByUserId: self::USER_ID,
      expiresAt: new DateTimeImmutable('2026-02-01T00:00:00+00:00'),
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-05T00:00:00+00:00'),
      roleIds: ['role-1'],
      acceptUrl: 'https://app.fireguard.test/invitations/token',
    );
  }
  // #endregion
}
