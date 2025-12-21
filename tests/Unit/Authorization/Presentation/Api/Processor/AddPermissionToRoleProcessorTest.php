<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use Authorization\Application\Port\Outbound\PermissionRepositoryPort;
use Authorization\Application\Port\Outbound\RoleRepositoryPort;
use Authorization\Domain\Model\Permission;
use Authorization\Domain\Model\Role;
use Authorization\Domain\ValueObject\PermissionId;
use Authorization\Domain\ValueObject\PermissionName;
use Authorization\Domain\ValueObject\RoleId;
use Authorization\Domain\ValueObject\RoleName;
use Authorization\Presentation\Api\Dto\AddPermissionInput;
use Authorization\Presentation\Api\Dto\RoleOutput;
use Authorization\Presentation\Api\Processor\AddPermissionToRoleProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test AddPermissionToRoleProcessorTest.
 *
 * @category Processor Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AddPermissionToRoleProcessor::class)]
final class AddPermissionToRoleProcessorTest extends TestCase
{
  // #region Properties
  private RoleRepositoryPort&MockObject $roleRepository;

  private PermissionRepositoryPort&MockObject $permissionRepository;

  private AddPermissionToRoleProcessor $processor;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    $this->roleRepository = $this->createMock(RoleRepositoryPort::class);
    $this->permissionRepository = $this->createMock(PermissionRepositoryPort::class);
    $this->processor = new AddPermissionToRoleProcessor(
      $this->roleRepository,
      $this->permissionRepository,
    );
  }
  // #endregion

  // #region Tests

  /**
   * Test successfully adding a permission to a role.
   */
  #[Test]
  public function testAddPermissionToRoleSuccessfully(): void
  {
    // Arrange
    $roleId = '550e8400-e29b-41d4-a716-446655440000';
    $permissionId = '660e8400-e29b-41d4-a716-446655440000';

    $role = Role::create(
      id: new RoleId($roleId),
      name: new RoleName('admin'),
      description: 'Admin role',
    );

    $permission = Permission::create(
      id: new PermissionId($permissionId),
      name: new PermissionName('users.create'),
      description: 'Create users',
    );

    $input = new AddPermissionInput();
    $input->permissionId = $permissionId;

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

    $operation = new Post();

    // Act
    $output = $this->processor->process(
      $input,
      $operation,
      ['roleId' => $roleId],
    );

    // Assert
    $this->assertInstanceOf(RoleOutput::class, $output);
    $this->assertEquals($roleId, $output->id);
    $this->assertEquals('admin', $output->name);
  }

  /**
   * Test adding permission to non-existent role throws exception.
   */
  #[Test]
  public function testAddPermissionToNonExistentRoleThrowsException(): void
  {
    // Arrange
    $roleId = '550e8400-e29b-41d4-a716-446655440000';
    $permissionId = '660e8400-e29b-41d4-a716-446655440000';

    $input = new AddPermissionInput();
    $input->permissionId = $permissionId;

    $this->roleRepository
      ->expects($this->once())
      ->method('findById')
      ->willReturn(null);

    $operation = new Post();

    // Assert
    $this->expectException(\Authorization\Domain\Exception\RoleNotFoundException::class);

    // Act
    $this->processor->process(
      $input,
      $operation,
      ['roleId' => $roleId],
    );
  }

  /**
   * Test adding non-existent permission throws exception.
   */
  #[Test]
  public function testAddNonExistentPermissionThrowsException(): void
  {
    // Arrange
    $roleId = '550e8400-e29b-41d4-a716-446655440000';
    $permissionId = '660e8400-e29b-41d4-a716-446655440000';

    $role = Role::create(
      id: new RoleId($roleId),
      name: new RoleName('admin'),
      description: 'Admin role',
    );

    $input = new AddPermissionInput();
    $input->permissionId = $permissionId;

    $this->roleRepository
      ->expects($this->once())
      ->method('findById')
      ->willReturn($role);

    $this->permissionRepository
      ->expects($this->once())
      ->method('findById')
      ->willReturn(null);

    $operation = new Post();

    // Assert
    $this->expectException(\Authorization\Domain\Exception\PermissionNotFoundException::class);

    // Act
    $this->processor->process(
      $input,
      $operation,
      ['roleId' => $roleId],
    );
  }

  // #endregion
}
