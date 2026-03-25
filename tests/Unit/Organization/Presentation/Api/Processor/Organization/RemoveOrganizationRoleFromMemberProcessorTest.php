<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Delete;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Organization\RemoveOrganizationRoleFromMember\RemoveOrganizationRoleFromMemberCommand;
use Organization\Domain\Exception\{OrganizationMemberNotFoundException, OrganizationRoleNotFoundException};
use Organization\Presentation\Api\Processor\Organization\RemoveOrganizationRoleFromMemberProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

#[CoversClass(RemoveOrganizationRoleFromMemberProcessor::class)]
final class RemoveOrganizationRoleFromMemberProcessorTest extends TestCase
{
  #[Test]
  public function testProcessThrowsWhenUriVariablesMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441400'));

    $processor = new RemoveOrganizationRoleFromMemberProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      authorization: $this->createMock(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Delete(), [
      'organizationId' => '550e8400-e29b-41d4-a716-446655441410',
      'memberId' => '550e8400-e29b-41d4-a716-446655441412',
    ]);
  }

  #[Test]
  public function testProcessThrowsWhenPermissionIsMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441400'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('550e8400-e29b-41d4-a716-446655441400', '550e8400-e29b-41d4-a716-446655441410', 'organization.roles.manage')
      ->willReturn(false);

    $processor = new RemoveOrganizationRoleFromMemberProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Delete(), [
      'organizationId' => '550e8400-e29b-41d4-a716-446655441410',
      'memberId' => '550e8400-e29b-41d4-a716-446655441412',
      'roleId' => '550e8400-e29b-41d4-a716-446655441411',
    ]);
  }

  #[Test]
  public function testProcessDispatchesCommandAndReturnsNull(): void
  {
    $security = $this->createMock(Security::class);
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
      ->with(self::callback(static function (RemoveOrganizationRoleFromMemberCommand $command): bool {
        return '550e8400-e29b-41d4-a716-446655441410' === $command->organizationId
          && '550e8400-e29b-41d4-a716-446655441412' === $command->memberId
          && '550e8400-e29b-41d4-a716-446655441411' === $command->roleId;
      }));

    $processor = new RemoveOrganizationRoleFromMemberProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $result = $processor->process(null, new Delete(), [
      'organizationId' => '550e8400-e29b-41d4-a716-446655441410',
      'memberId' => '550e8400-e29b-41d4-a716-446655441412',
      'roleId' => '550e8400-e29b-41d4-a716-446655441411',
    ]);

    self::assertNull($result);
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenRoleAbsent(): void
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441400'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(OrganizationRoleNotFoundException::withId('550e8400-e29b-41d4-a716-446655441411'));

    $processor = new RemoveOrganizationRoleFromMemberProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(null, new Delete(), [
      'organizationId' => '550e8400-e29b-41d4-a716-446655441410',
      'memberId' => '550e8400-e29b-41d4-a716-446655441412',
      'roleId' => '550e8400-e29b-41d4-a716-446655441411',
    ]);
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenMemberAbsent(): void
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441400'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(OrganizationMemberNotFoundException::withId('550e8400-e29b-41d4-a716-446655441412'));

    $processor = new RemoveOrganizationRoleFromMemberProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(null, new Delete(), [
      'organizationId' => '550e8400-e29b-41d4-a716-446655441410',
      'memberId' => '550e8400-e29b-41d4-a716-446655441412',
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
}
