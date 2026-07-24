<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationLastAdminGuardPort, OrganizationPermissionGrantGuardPort};
use Organization\Application\UseCase\Command\Organization\UpdateOrganizationRole\{UpdateOrganizationRoleCommand, UpdateOrganizationRoleResult};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationLastAdminException};
use Organization\Presentation\Api\Dto\Input\Organization\UpdateOrganizationRoleInput;
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationPermissionOutput, OrganizationRoleOutput};
use Organization\Presentation\Api\Processor\Organization\UpdateOrganizationRoleProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException};

#[CoversClass(UpdateOrganizationRoleProcessor::class)]
final class UpdateOrganizationRoleProcessorTest extends TestCase
{
  #[Test]
  public function testProcessThrowsWhenUnauthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new UpdateOrganizationRoleProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      grantGuard: $this->createStub(OrganizationPermissionGrantGuardPort::class),
      lastAdminGuard: $this->createStub(OrganizationLastAdminGuardPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process($this->createInput(), new Patch(), [
      'organizationId' => '550e8400-e29b-41d4-a716-446655441510',
      'roleId' => '550e8400-e29b-41d4-a716-446655441511',
    ]);
  }

  #[Test]
  public function testProcessThrowsWhenOrganizationIdMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441500'));

    $processor = new UpdateOrganizationRoleProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      grantGuard: $this->createStub(OrganizationPermissionGrantGuardPort::class),
      lastAdminGuard: $this->createStub(OrganizationLastAdminGuardPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process($this->createInput(), new Patch(), ['roleId' => '550e8400-e29b-41d4-a716-446655441511']);
  }

  #[Test]
  public function testProcessThrowsWhenRoleIdMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441500'));

    $processor = new UpdateOrganizationRoleProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      grantGuard: $this->createStub(OrganizationPermissionGrantGuardPort::class),
      lastAdminGuard: $this->createStub(OrganizationLastAdminGuardPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process($this->createInput(), new Patch(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441510']);
  }

  #[Test]
  public function testProcessThrowsWhenPermissionIsMissingAndSkipsGuards(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441500'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('550e8400-e29b-41d4-a716-446655441500', '550e8400-e29b-41d4-a716-446655441510', 'organization.roles.manage')
      ->willReturn(false);

    /** @var OrganizationPermissionGrantGuardPort&MockObject $grantGuard */
    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::never())->method('assertCanGrantPermissions');

    /** @var OrganizationLastAdminGuardPort&MockObject $lastAdminGuard */
    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::never())->method('assertCanUpdateRolePermissions');

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new UpdateOrganizationRoleProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      grantGuard: $grantGuard,
      lastAdminGuard: $lastAdminGuard,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process($this->createInput(), new Patch(), [
      'organizationId' => '550e8400-e29b-41d4-a716-446655441510',
      'roleId' => '550e8400-e29b-41d4-a716-446655441511',
    ]);
  }

  #[Test]
  public function testProcessCallsBothGuardsOnceThenDispatchesAndMapsRoleOutput(): void
  {
    $createdAt = new DateTimeImmutable('2026-01-01T10:00:00+00:00');
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441500'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    /** @var OrganizationPermissionGrantGuardPort&MockObject $grantGuard */
    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::once())
      ->method('assertCanGrantPermissions')
      ->with(
        '550e8400-e29b-41d4-a716-446655441500',
        '550e8400-e29b-41d4-a716-446655441510',
        ['organization.read', 'organization.members.read'],
      );

    /** @var OrganizationLastAdminGuardPort&MockObject $lastAdminGuard */
    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::once())
      ->method('assertCanUpdateRolePermissions')
      ->with(
        '550e8400-e29b-41d4-a716-446655441510',
        '550e8400-e29b-41d4-a716-446655441511',
        ['organization.read', 'organization.members.read'],
      );

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (UpdateOrganizationRoleCommand $command): bool {
        return '550e8400-e29b-41d4-a716-446655441510' === $command->organizationId
          && '550e8400-e29b-41d4-a716-446655441511' === $command->roleId
          && ['organization.read', 'organization.members.read'] === $command->permissions
          && 'Inspects equipment' === $command->description;
      }))
      ->willReturn(new UpdateOrganizationRoleResult(
        id: '550e8400-e29b-41d4-a716-446655441511',
        organizationId: '550e8400-e29b-41d4-a716-446655441510',
        name: 'inspector',
        permissions: ['organization.read', 'organization.members.read'],
        isSystem: false,
        createdAt: $createdAt,
        description: 'Inspects equipment',
      ));

    $processor = new UpdateOrganizationRoleProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      grantGuard: $grantGuard,
      lastAdminGuard: $lastAdminGuard,
      security: $security,
    );

    $output = $processor->process($this->createInput(description: 'Inspects equipment'), new Patch(), [
      'organizationId' => '550e8400-e29b-41d4-a716-446655441510',
      'roleId' => '550e8400-e29b-41d4-a716-446655441511',
    ]);

    self::assertInstanceOf(OrganizationRoleOutput::class, $output);
    self::assertSame('550e8400-e29b-41d4-a716-446655441511', $output->id);
    self::assertSame('550e8400-e29b-41d4-a716-446655441510', $output->organizationId);
    self::assertSame('inspector', $output->name);
    self::assertCount(2, $output->permissions);
    self::assertInstanceOf(OrganizationPermissionOutput::class, $output->permissions[0]);
    self::assertSame('organization.read', $output->permissions[0]->name);
    self::assertSame('organization.members.read', $output->permissions[1]->name);
    self::assertFalse($output->isSystem);
    self::assertSame($createdAt->format('c'), $output->createdAt);
    self::assertSame('Inspects equipment', $output->description);
  }

  #[Test]
  public function testProcessThrowsForbiddenWhenGrantGuardRefusesAndNeverDispatches(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441500'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var OrganizationPermissionGrantGuardPort&MockObject $grantGuard */
    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::once())
      ->method('assertCanGrantPermissions')
      ->willThrowException(OrganizationAccessDeniedException::cannotGrantPermission('organization.*'));

    /** @var OrganizationLastAdminGuardPort&MockObject $lastAdminGuard */
    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::never())->method('assertCanUpdateRolePermissions');

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new UpdateOrganizationRoleProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      grantGuard: $grantGuard,
      lastAdminGuard: $lastAdminGuard,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process($this->createInput(), new Patch(), [
      'organizationId' => '550e8400-e29b-41d4-a716-446655441510',
      'roleId' => '550e8400-e29b-41d4-a716-446655441511',
    ]);
  }

  #[Test]
  public function testProcessThrowsConflictWhenLastAdminGuardRefusesAndNeverDispatches(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441500'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var OrganizationPermissionGrantGuardPort&MockObject $grantGuard */
    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::once())->method('assertCanGrantPermissions');

    /** @var OrganizationLastAdminGuardPort&MockObject $lastAdminGuard */
    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::once())
      ->method('assertCanUpdateRolePermissions')
      ->willThrowException(OrganizationLastAdminException::cannotRemoveLastAdmin());

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new UpdateOrganizationRoleProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      grantGuard: $grantGuard,
      lastAdminGuard: $lastAdminGuard,
      security: $security,
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process($this->createInput(), new Patch(), [
      'organizationId' => '550e8400-e29b-41d4-a716-446655441510',
      'roleId' => '550e8400-e29b-41d4-a716-446655441511',
    ]);
  }

  private function createInput(?string $description = null): UpdateOrganizationRoleInput
  {
    $input = new UpdateOrganizationRoleInput();
    $input->permissions = ['organization.read', 'organization.members.read'];
    $input->description = $description;

    return $input;
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
}
