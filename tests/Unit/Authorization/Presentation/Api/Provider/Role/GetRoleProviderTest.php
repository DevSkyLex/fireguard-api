<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Presentation\Api\Provider\Role;

use ApiPlatform\Metadata\Get;
use Authorization\Application\UseCase\Query\Permission\GetPermission\GetPermissionResult;
use Authorization\Application\UseCase\Query\Role\GetRole\{GetRoleQuery, GetRoleResult};
use Authorization\Domain\Exception\RoleNotFoundException;
use Authorization\Presentation\Api\Dto\Output\Role\RoleOutput;
use Authorization\Presentation\Api\Provider\Role\GetRoleProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;

/**
 * Test GetRoleProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetRoleProvider::class)]
final class GetRoleProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProvideReturnsNullWhenIdMissing(): void
  {
    $provider = new GetRoleProvider($this->createStub(QueryBusPort::class));

    $result = $provider->provide(new Get(), ['id' => null]);

    self::assertNull($result);
  }

  #[Test]
  public function testProvideReturnsNullWhenRoleMissing(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetRoleQuery::class))
      ->willThrowException(RoleNotFoundException::withId(roleId: 'role-123'));

    $provider = new GetRoleProvider($queryBus);

    $result = $provider->provide(new Get(), ['id' => 'role-123']);

    self::assertNull($result);
  }

  #[Test]
  public function testProvideMapsRoleOutput(): void
  {
    $permissionId = '660e8400-e29b-41d4-a716-446655440000';

    $roleResult = new GetRoleResult(
      id: '550e8400-e29b-41d4-a716-446655440000',
      name: 'admin',
      description: 'Admin role',
      isSystem: false,
      createdAt: '2025-01-01 12:00:00',
      permissions: [
        new GetPermissionResult(
          id: $permissionId,
          name: 'users.create',
          description: 'Create users',
          createdAt: '2025-01-01 12:00:00',
        ),
      ],
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetRoleQuery::class))
      ->willReturn($roleResult);

    $provider = new GetRoleProvider($queryBus);

    $output = $provider->provide(new Get(), ['id' => '550e8400-e29b-41d4-a716-446655440000']);

    self::assertInstanceOf(RoleOutput::class, $output);
    self::assertSame('admin', $output->name);
    self::assertCount(1, $output->permissions);
    self::assertSame($permissionId, $output->permissions[0]->id);
  }
  // #endregion
}
