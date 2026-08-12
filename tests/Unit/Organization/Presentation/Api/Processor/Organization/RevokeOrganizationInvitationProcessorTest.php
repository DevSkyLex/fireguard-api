<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Organization\RevokeOrganizationInvitation\{
  RevokeOrganizationInvitationCommand,
  RevokeOrganizationInvitationResult
};
use Organization\Domain\Exception\OrganizationInvitationNotFoundException;
use Organization\Presentation\Api\Processor\Organization\RevokeOrganizationInvitationProcessor;
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
  NotFoundHttpException
};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

/**
 * Test RevokeOrganizationInvitationProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RevokeOrganizationInvitationProcessor::class)]
final class RevokeOrganizationInvitationProcessorTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string INVITATION_ID = '550e8400-e29b-41d4-a716-446655440040';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440001';
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
  public function testProcessRevokesTheInvitationAndMapsTheOutput(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (RevokeOrganizationInvitationCommand $command): bool => self::ORGANIZATION_ID === $command->organizationId
        && self::INVITATION_ID === $command->invitationId
        && self::USER_ID === $command->revokedByUserId))
      ->willReturn($this->invitationResult());

    $output = $this->createProcessor($commandBus)->process(null, new Post(), $this->uriVariables());

    self::assertSame(self::INVITATION_ID, $output->id);
    self::assertSame('revoked', $output->status);
    self::assertSame(self::USER_ID, $output->revokedByUserId);
    self::assertSame('2026-01-03T00:00:00+00:00', $output->revokedAt);
    self::assertNull($output->acceptedAt);
    self::assertSame(['role-1'], $output->roleIds);
  }

  #[Test]
  public function testProcessThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new RevokeOrganizationInvitationProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->authorization(true),
      security: $security,
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
    $processor = new RevokeOrganizationInvitationProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->authorization(false),
      security: $this->securityWithUser(),
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing Organization.members.manage permission.');

    $processor->process(null, new Post(), $this->uriVariables());
  }

  #[Test]
  public function testProcessMapsAMissingInvitationToHttp404(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($this->wrapped(
      OrganizationInvitationNotFoundException::withId(self::INVITATION_ID),
      new RevokeOrganizationInvitationCommand(
        organizationId: self::ORGANIZATION_ID,
        invitationId: self::INVITATION_ID,
        revokedByUserId: self::USER_ID,
      ),
    ));

    $this->expectException(NotFoundHttpException::class);

    $this->createProcessor($commandBus)->process(null, new Post(), $this->uriVariables());
  }

  #[Test]
  public function testProcessMapsAnInvalidArgumentToHttp400(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($this->wrapped(
      new InvalidArgumentException('The invitation was already accepted.'),
      new RevokeOrganizationInvitationCommand(
        organizationId: self::ORGANIZATION_ID,
        invitationId: self::INVITATION_ID,
        revokedByUserId: self::USER_ID,
      ),
    ));

    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor($commandBus)->process(null, new Post(), $this->uriVariables());
  }

  #[Test]
  public function testProcessRethrowsMessengerFailureWhenNoDomainExceptionIsRecognised(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(new RuntimeException('Bus transport is down.')),
    );

    $this->expectException(MessengerRuntimeException::class);

    $this->createProcessor($commandBus)->process(null, new Post(), $this->uriVariables());
  }

  /**
   * @return array<string, string>
   */
  private function uriVariables(): array
  {
    return ['organizationId' => self::ORGANIZATION_ID, 'invitationId' => self::INVITATION_ID];
  }

  private function createProcessor(?CommandBusPort $commandBus = null): RevokeOrganizationInvitationProcessor
  {
    return new RevokeOrganizationInvitationProcessor(
      commandBus: $commandBus ?? $this->createStub(CommandBusPort::class),
      authorization: $this->authorization(true),
      security: $this->securityWithUser(),
    );
  }

  private function authorization(bool $granted): OrganizationAuthorizationPort
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn($granted);

    return $authorization;
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

  private function invitationResult(): RevokeOrganizationInvitationResult
  {
    return new RevokeOrganizationInvitationResult(
      invitationId: self::INVITATION_ID,
      organizationId: self::ORGANIZATION_ID,
      email: 'invitee@example.com',
      status: 'revoked',
      invitedByUserId: self::USER_ID,
      revokedByUserId: self::USER_ID,
      expiresAt: new DateTimeImmutable('2026-02-01T00:00:00+00:00'),
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-03T00:00:00+00:00'),
      acceptedAt: null,
      revokedAt: new DateTimeImmutable('2026-01-03T00:00:00+00:00'),
      roleIds: ['role-1'],
    );
  }

  private function wrapped(Throwable $domainFailure, object $message): MessengerRuntimeException
  {
    return MessengerRuntimeException::wrap(
      new HandlerFailedException(new Envelope($message), [$domainFailure]),
    );
  }
  // #endregion
}
