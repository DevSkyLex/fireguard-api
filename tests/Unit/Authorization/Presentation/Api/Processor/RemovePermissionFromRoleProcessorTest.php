<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Presentation\Api\Processor;

use ApiPlatform\Metadata\Delete;
use Authorization\Application\Port\Outbound\PermissionRepositoryPort;
use Authorization\Application\Port\Outbound\RoleRepositoryPort;
use Authorization\Domain\Model\Permission;
use Authorization\Domain\Model\Role;
use Authorization\Domain\ValueObject\PermissionId;
use Authorization\Domain\ValueObject\PermissionName;
use Authorization\Domain\ValueObject\RoleId;
use Authorization\Domain\ValueObject\RoleName;
use Authorization\Presentation\Api\Dto\RoleOutput;
use Authorization\Presentation\Api\Processor\RemovePermissionFromRoleProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test RemovePermissionFromRoleProcessorTest.
 *
 * @category Processor Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RemovePermissionFromRoleProcessor::class)]
final class RemovePermissionFromRoleProcessorTest extends TestCase
{
    // #region Properties
    private RoleRepositoryPort&MockObject $roleRepository;
    private PermissionRepositoryPort&MockObject $permissionRepository;
    private RemovePermissionFromRoleProcessor $processor;
    // #endregion

    // #region Setup
    protected function setUp(): void
    {
        $this->roleRepository = $this->createMock(RoleRepositoryPort::class);
        $this->permissionRepository = $this->createMock(PermissionRepositoryPort::class);
        $this->processor = new RemovePermissionFromRoleProcessor(
            $this->roleRepository,
            $this->permissionRepository
        );
    }
    // #endregion

    // #region Tests

    /**
     * Test successfully removing a permission from a role.
     */
    #[Test]
    public function testRemovePermissionFromRoleSuccessfully(): void
    {
        // Arrange
        $roleId = '550e8400-e29b-41d4-a716-446655440000';
        $permissionId = '660e8400-e29b-41d4-a716-446655440000';

        $permission = Permission::create(
            id: new PermissionId($permissionId),
            name: new PermissionName('users.create'),
            description: 'Create users'
        );

        $role = Role::create(
            id: new RoleId($roleId),
            name: new RoleName('admin'),
            description: 'Admin role'
        );
        $role->addPermission($permission);

        $this->roleRepository
          ->expects($this->once())
          ->method('findById')
          ->with($this->callback(fn (RoleId $id) => $id->value === $roleId))
          ->willReturn($role);

        $this->permissionRepository
          ->expects($this->once())
          ->method('findById')
          ->with($this->callback(fn (PermissionId $id) => $id->value === $permissionId))
          ->willReturn($permission);

        $this->roleRepository
          ->expects($this->once())
          ->method('save')
          ->with($role);

        $operation = new Delete();

        // Act
        $output = $this->processor->process(
            null,
            $operation,
            ['roleId' => $roleId, 'permissionId' => $permissionId]
        );

        // Assert
        $this->assertInstanceOf(RoleOutput::class, $output);
        $this->assertEquals($roleId, $output->id);
        $this->assertEmpty($output->permissions);
    }

    /**
     * Test removing permission from non-existent role throws exception.
     */
    #[Test]
    public function testRemovePermissionFromNonExistentRoleThrowsException(): void
    {
        // Arrange
        $roleId = '550e8400-e29b-41d4-a716-446655440000';
        $permissionId = '660e8400-e29b-41d4-a716-446655440000';

        $this->roleRepository
          ->expects($this->once())
          ->method('findById')
          ->willReturn(null);

        $operation = new Delete();

        // Assert
        $this->expectException(\Authorization\Domain\Exception\RoleNotFoundException::class);

        // Act
        $this->processor->process(
            null,
            $operation,
            ['roleId' => $roleId, 'permissionId' => $permissionId]
        );
    }

    /**
     * Test removing non-existent permission throws exception.
     */
    #[Test]
    public function testRemoveNonExistentPermissionThrowsException(): void
    {
        // Arrange
        $roleId = '550e8400-e29b-41d4-a716-446655440000';
        $permissionId = '660e8400-e29b-41d4-a716-446655440000';

        $role = Role::create(
            id: new RoleId($roleId),
            name: new RoleName('admin'),
            description: 'Admin role'
        );

        $this->roleRepository
          ->expects($this->once())
          ->method('findById')
          ->willReturn($role);

        $this->permissionRepository
          ->expects($this->once())
          ->method('findById')
          ->willReturn(null);

        $operation = new Delete();

        // Assert
        $this->expectException(\Authorization\Domain\Exception\PermissionNotFoundException::class);

        // Act
        $this->processor->process(
            null,
            $operation,
            ['roleId' => $roleId, 'permissionId' => $permissionId]
        );
    }

    // #endregion
}
