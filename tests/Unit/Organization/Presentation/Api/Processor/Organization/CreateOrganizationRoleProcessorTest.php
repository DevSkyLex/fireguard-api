<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Organization\CreateOrganizationRole\{CreateOrganizationRoleCommand, CreateOrganizationRoleResult};
use Organization\Presentation\Api\Dto\Input\Organization\CreateOrganizationRoleInput;
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationPermissionOutput, OrganizationRoleOutput};
use Organization\Presentation\Api\Processor\Organization\CreateOrganizationRoleProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

#[CoversClass(CreateOrganizationRoleProcessor::class)]
final class CreateOrganizationRoleProcessorTest extends TestCase
{
  #[Test]
  public function testProcessThrowsWhenPermissionIsMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441300'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('550e8400-e29b-41d4-a716-446655441300', '550e8400-e29b-41d4-a716-446655441310', 'organization.roles.manage')
      ->willReturn(false);

    $processor = new CreateOrganizationRoleProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $input = new CreateOrganizationRoleInput();
    $input->name = 'inspector';
    $input->permissions = ['organization.read'];

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process($input, new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441310']);
  }

  #[Test]
  public function testProcessDispatchesCommandAndMapsRoleOutput(): void
  {
    $createdAt = new DateTimeImmutable('2026-01-01T10:00:00+00:00');
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441300'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (CreateOrganizationRoleCommand $command): bool {
        return '550e8400-e29b-41d4-a716-446655441310' === $command->organizationId
          && 'inspector' === $command->name
          && ['organization.read', 'organization.members.read'] === $command->permissions
          && null === $command->description;
      }))
      ->willReturn(new CreateOrganizationRoleResult(
        id: '550e8400-e29b-41d4-a716-446655441311',
        organizationId: '550e8400-e29b-41d4-a716-446655441310',
        name: 'inspector',
        permissions: ['organization.read', 'organization.members.read'],
        isSystem: false,
        createdAt: $createdAt,
        description: 'Inspects equipment',
      ));

    $processor = new CreateOrganizationRoleProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $input = new CreateOrganizationRoleInput();
    $input->name = 'inspector';
    $input->permissions = ['organization.read', 'organization.members.read'];

    $output = $processor->process($input, new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441310']);

    self::assertInstanceOf(OrganizationRoleOutput::class, $output);
    self::assertSame('550e8400-e29b-41d4-a716-446655441311', $output->id);
    self::assertSame('550e8400-e29b-41d4-a716-446655441310', $output->organizationId);
    self::assertSame('inspector', $output->name);
    self::assertCount(2, $output->permissions);
    self::assertInstanceOf(OrganizationPermissionOutput::class, $output->permissions[0]);
    self::assertSame('organization.read', $output->permissions[0]->name);
    self::assertSame('View organization details', $output->permissions[0]->description);
    self::assertSame('organization.members.read', $output->permissions[1]->name);
    self::assertSame('View organization members', $output->permissions[1]->description);
    self::assertFalse($output->isSystem);
    self::assertSame($createdAt->format('c'), $output->createdAt);
    self::assertSame('Inspects equipment', $output->description);
  }

  #[Test]
  public function testProcessThrowsWhenOrganizationIdMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441300'));

    $processor = new CreateOrganizationRoleProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      authorization: $this->createMock(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(new CreateOrganizationRoleInput(), new Post(), []);
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
