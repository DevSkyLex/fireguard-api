<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Delete;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\UseCase\Command\Organization\LeaveOrganization\{LeaveOrganizationCommand, LeaveOrganizationResult};
use Organization\Domain\Exception\{
  OrganizationLastAdminException,
  OrganizationMemberNotFoundException,
  OrganizationNotFoundException,
  OrganizationOwnerCannotLeaveException
};
use Organization\Presentation\Api\Processor\Organization\LeaveOrganizationProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[CoversClass(LeaveOrganizationProcessor::class)]
final class LeaveOrganizationProcessorTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655441410';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441400';

  #[Test]
  public function testProcessThrowsWhenUnauthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new LeaveOrganizationProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Delete(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsWhenOrganizationIdentifierIsMissing(): void
  {
    $processor = $this->createProcessor($this->createStub(CommandBusPort::class));

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Delete(), []);
  }

  #[Test]
  public function testProcessDispatchesCommandWithTheActingUserAndReturnsNull(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (object $command): bool => $command instanceof LeaveOrganizationCommand
        && self::ORGANIZATION_ID === $command->organizationId
        && self::USER_ID === $command->actingUserId))
      ->willReturn(new LeaveOrganizationResult(
        memberId: '550e8400-e29b-41d4-a716-446655441412',
        organizationId: self::ORGANIZATION_ID,
      ));

    $processor = $this->createProcessor($commandBus);

    $result = $processor->process(null, new Delete(), ['organizationId' => self::ORGANIZATION_ID]);

    self::assertNull($result);
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenOrganizationIsMissing(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(OrganizationNotFoundException::withId(self::ORGANIZATION_ID));

    $processor = $this->createProcessor($commandBus);

    $this->expectException(NotFoundHttpException::class);

    $processor->process(null, new Delete(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenActingUserIsNotAnActiveMember(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(OrganizationMemberNotFoundException::forUserInOrganization(self::USER_ID, self::ORGANIZATION_ID));

    $processor = $this->createProcessor($commandBus);

    $this->expectException(NotFoundHttpException::class);

    $processor->process(null, new Delete(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsConflictWhenActingUserIsTheOwner(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(OrganizationOwnerCannotLeaveException::mustTransferOwnershipFirst());

    $processor = $this->createProcessor($commandBus);

    $this->expectException(ConflictHttpException::class);

    $processor->process(null, new Delete(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsConflictWhenActingUserIsTheLastAdministrator(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(OrganizationLastAdminException::cannotRemoveLastAdmin());

    $processor = $this->createProcessor($commandBus);

    $this->expectException(ConflictHttpException::class);

    $processor->process(null, new Delete(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsConflictWhenOwnerCannotLeaveIsWrappedInMessengerRuntimeException(): void
  {
    $handlerFailure = new HandlerFailedException(
      new Envelope(new LeaveOrganizationCommand(organizationId: self::ORGANIZATION_ID, actingUserId: self::USER_ID)),
      [OrganizationOwnerCannotLeaveException::mustTransferOwnershipFirst()],
    );

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = $this->createProcessor($commandBus);

    $this->expectException(ConflictHttpException::class);

    $processor->process(null, new Delete(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenMemberNotFoundIsWrappedInMessengerRuntimeException(): void
  {
    $handlerFailure = new HandlerFailedException(
      new Envelope(new LeaveOrganizationCommand(organizationId: self::ORGANIZATION_ID, actingUserId: self::USER_ID)),
      [OrganizationMemberNotFoundException::forUserInOrganization(self::USER_ID, self::ORGANIZATION_ID)],
    );

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = $this->createProcessor($commandBus);

    $this->expectException(NotFoundHttpException::class);

    $processor->process(null, new Delete(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessRethrowsMessengerFailureWhenNoDomainExceptionIsRecognised(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap(new RuntimeException('Bus transport is down.')));

    $processor = $this->createProcessor($commandBus);

    $this->expectException(MessengerRuntimeException::class);

    $processor->process(null, new Delete(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  private function createProcessor(CommandBusPort $commandBus): LeaveOrganizationProcessor
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    return new LeaveOrganizationProcessor(
      commandBus: $commandBus,
      security: $security,
    );
  }

  private function createSecurityUser(): SecurityUser
  {
    return new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
