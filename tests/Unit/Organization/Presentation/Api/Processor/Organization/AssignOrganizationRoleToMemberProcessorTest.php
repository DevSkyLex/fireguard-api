<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationPermissionGrantGuardPort};
use Organization\Application\UseCase\Command\Organization\AssignOrganizationRoleToMember\{AssignOrganizationRoleToMemberCommand, AssignOrganizationRoleToMemberResult};
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use Organization\Presentation\Api\Dto\Input\Organization\AssignOrganizationRoleInput;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationMemberOutput;
use Organization\Presentation\Api\Processor\Organization\AssignOrganizationRoleToMemberProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

#[CoversClass(AssignOrganizationRoleToMemberProcessor::class)]
final class AssignOrganizationRoleToMemberProcessorTest extends TestCase
{
  #[Test]
  public function testProcessThrowsWhenUriVariablesMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441400'));

    $processor = new AssignOrganizationRoleToMemberProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      grantGuard: $this->createStub(OrganizationPermissionGrantGuardPort::class),
      security: $security,
    );

    $input = new AssignOrganizationRoleInput();
    $input->roleId = '550e8400-e29b-41d4-a716-446655441411';

    $this->expectException(BadRequestHttpException::class);

    $processor->process($input, new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441410']);
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

    $processor = new AssignOrganizationRoleToMemberProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $authorization,
      grantGuard: $this->createStub(OrganizationPermissionGrantGuardPort::class),
      security: $security,
    );

    $input = new AssignOrganizationRoleInput();
    $input->roleId = '550e8400-e29b-41d4-a716-446655441411';

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process($input, new Post(), [
      'organizationId' => '550e8400-e29b-41d4-a716-446655441410',
      'memberId' => '550e8400-e29b-41d4-a716-446655441412',
    ]);
  }

  #[Test]
  public function testProcessDispatchesCommandAndMapsOutput(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441400'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (AssignOrganizationRoleToMemberCommand $command): bool {
        return '550e8400-e29b-41d4-a716-446655441410' === $command->organizationId
          && '550e8400-e29b-41d4-a716-446655441412' === $command->memberId
          && '550e8400-e29b-41d4-a716-446655441411' === $command->roleId;
      }))
      ->willReturn(new AssignOrganizationRoleToMemberResult(
        memberId: '550e8400-e29b-41d4-a716-446655441412',
        organizationId: '550e8400-e29b-41d4-a716-446655441410',
        roleId: '550e8400-e29b-41d4-a716-446655441411',
        roleIds: ['550e8400-e29b-41d4-a716-446655441411', '550e8400-e29b-41d4-a716-446655441413'],
        userId: '550e8400-e29b-41d4-a716-446655441401',
        isActive: true,
        joinedAt: new DateTimeImmutable('-2 days'),
      ));

    $processor = new AssignOrganizationRoleToMemberProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      grantGuard: $this->createStub(OrganizationPermissionGrantGuardPort::class),
      security: $security,
    );

    $input = new AssignOrganizationRoleInput();
    $input->roleId = '550e8400-e29b-41d4-a716-446655441411';

    $output = $processor->process($input, new Post(), [
      'organizationId' => '550e8400-e29b-41d4-a716-446655441410',
      'memberId' => '550e8400-e29b-41d4-a716-446655441412',
    ]);

    self::assertInstanceOf(OrganizationMemberOutput::class, $output);
    self::assertSame('550e8400-e29b-41d4-a716-446655441412', $output->id);
    self::assertSame('550e8400-e29b-41d4-a716-446655441410', $output->organizationId);
    self::assertSame('550e8400-e29b-41d4-a716-446655441401', $output->userId);
    self::assertSame(['550e8400-e29b-41d4-a716-446655441411', '550e8400-e29b-41d4-a716-446655441413'], $output->roleIds);
    self::assertTrue($output->isActive);
  }

  #[Test]
  public function testProcessThrowsForbiddenWhenAssigningRoleActorCannotGrant(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441400'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var OrganizationPermissionGrantGuardPort&MockObject $grantGuard */
    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::once())
      ->method('assertCanAssignRoles')
      ->willThrowException(OrganizationAccessDeniedException::cannotGrantPermission('organization.*'));

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new AssignOrganizationRoleToMemberProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      grantGuard: $grantGuard,
      security: $security,
    );

    $input = new AssignOrganizationRoleInput();
    $input->roleId = '550e8400-e29b-41d4-a716-446655441411';

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process($input, new Post(), [
      'organizationId' => '550e8400-e29b-41d4-a716-446655441410',
      'memberId' => '550e8400-e29b-41d4-a716-446655441412',
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
}
