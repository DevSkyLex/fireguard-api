<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Delete;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationLastAdminGuardPort};
use Organization\Application\UseCase\Command\Organization\DeleteOrganizationRole\DeleteOrganizationRoleCommand;
use Organization\Domain\Exception\{OrganizationLastAdminException, OrganizationNotFoundException, OrganizationRoleNotFoundException};
use Organization\Presentation\Api\Processor\Organization\DeleteOrganizationRoleProcessor;
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
use Throwable;

#[CoversClass(DeleteOrganizationRoleProcessor::class)]
final class DeleteOrganizationRoleProcessorTest extends TestCase
{
  #[Test]
  public function testProcessThrowsWhenUnauthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new DeleteOrganizationRoleProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      lastAdminGuard: $this->createStub(OrganizationLastAdminGuardPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Delete(), [
      'organizationId' => '550e8400-e29b-41d4-a716-446655441410',
      'roleId' => '550e8400-e29b-41d4-a716-446655441411',
    ]);
  }

  #[Test]
  public function testProcessThrowsWhenUriVariablesMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441400'));

    $processor = new DeleteOrganizationRoleProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      lastAdminGuard: $this->createStub(OrganizationLastAdminGuardPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Delete(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441410']);
  }

  #[Test]
  public function testProcessThrowsWhenPermissionIsMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441400'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('550e8400-e29b-41d4-a716-446655441400', '550e8400-e29b-41d4-a716-446655441410', 'organization.roles.manage')
      ->willReturn(false);

    $processor = new DeleteOrganizationRoleProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $authorization,
      lastAdminGuard: $this->createStub(OrganizationLastAdminGuardPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Delete(), [
      'organizationId' => '550e8400-e29b-41d4-a716-446655441410',
      'roleId' => '550e8400-e29b-41d4-a716-446655441411',
    ]);
  }

  #[Test]
  public function testProcessDispatchesCommandAndReturnsNull(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441400'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (DeleteOrganizationRoleCommand $command): bool {
        return '550e8400-e29b-41d4-a716-446655441410' === $command->organizationId
          && '550e8400-e29b-41d4-a716-446655441411' === $command->roleId;
      }));

    $processor = new DeleteOrganizationRoleProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      lastAdminGuard: $this->createStub(OrganizationLastAdminGuardPort::class),
      security: $security,
    );

    $result = $processor->process(null, new Delete(), [
      'organizationId' => '550e8400-e29b-41d4-a716-446655441410',
      'roleId' => '550e8400-e29b-41d4-a716-446655441411',
    ]);

    self::assertNull($result);
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenRoleAbsent(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441400'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException($this->wrapped(
        OrganizationRoleNotFoundException::withId('550e8400-e29b-41d4-a716-446655441411'),
        new DeleteOrganizationRoleCommand(
          organizationId: '550e8400-e29b-41d4-a716-446655441410',
          roleId: '550e8400-e29b-41d4-a716-446655441411',
        ),
      ));

    $processor = new DeleteOrganizationRoleProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      lastAdminGuard: $this->createStub(OrganizationLastAdminGuardPort::class),
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(null, new Delete(), [
      'organizationId' => '550e8400-e29b-41d4-a716-446655441410',
      'roleId' => '550e8400-e29b-41d4-a716-446655441411',
    ]);
  }

  #[Test]
  public function testProcessRethrowsMessengerFailureWhenNoDomainExceptionIsRecognised(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441400'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(new RuntimeException('Bus transport is down.')),
    );

    $processor = new DeleteOrganizationRoleProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      lastAdminGuard: $this->createStub(OrganizationLastAdminGuardPort::class),
      security: $security,
    );

    $this->expectException(MessengerRuntimeException::class);

    $processor->process(null, new Delete(), [
      'organizationId' => '550e8400-e29b-41d4-a716-446655441410',
      'roleId' => '550e8400-e29b-41d4-a716-446655441411',
    ]);
  }

  #[Test]
  public function testProcessThrowsBadRequestWhenSystemRole(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441400'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException($this->wrapped(
        new InvalidArgumentException('System roles cannot be deleted.'),
        new DeleteOrganizationRoleCommand(
          organizationId: '550e8400-e29b-41d4-a716-446655441410',
          roleId: '550e8400-e29b-41d4-a716-446655441411',
        ),
      ));

    $processor = new DeleteOrganizationRoleProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      lastAdminGuard: $this->createStub(OrganizationLastAdminGuardPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Delete(), [
      'organizationId' => '550e8400-e29b-41d4-a716-446655441410',
      'roleId' => '550e8400-e29b-41d4-a716-446655441411',
    ]);
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenOrganizationAbsent(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441400'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException($this->wrapped(
        OrganizationNotFoundException::withId('550e8400-e29b-41d4-a716-446655441410'),
        new DeleteOrganizationRoleCommand(
          organizationId: '550e8400-e29b-41d4-a716-446655441410',
          roleId: '550e8400-e29b-41d4-a716-446655441411',
        ),
      ));

    $processor = new DeleteOrganizationRoleProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      lastAdminGuard: $this->createStub(OrganizationLastAdminGuardPort::class),
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(null, new Delete(), [
      'organizationId' => '550e8400-e29b-41d4-a716-446655441410',
      'roleId' => '550e8400-e29b-41d4-a716-446655441411',
    ]);
  }

  #[Test]
  public function testProcessThrowsConflictWhenDeletingLastAdminRole(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441400'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var OrganizationLastAdminGuardPort&MockObject $lastAdminGuard */
    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::once())
      ->method('assertCanDeleteRole')
      ->willThrowException(OrganizationLastAdminException::cannotUnassignLastAdminRole());

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new DeleteOrganizationRoleProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      lastAdminGuard: $lastAdminGuard,
      security: $security,
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(null, new Delete(), [
      'organizationId' => '550e8400-e29b-41d4-a716-446655441410',
      'roleId' => '550e8400-e29b-41d4-a716-446655441411',
    ]);
  }

  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }

  private function wrapped(Throwable $domainFailure, object $message): MessengerRuntimeException
  {
    return MessengerRuntimeException::wrap(
      new HandlerFailedException(new Envelope($message), [$domainFailure]),
    );
  }
}
