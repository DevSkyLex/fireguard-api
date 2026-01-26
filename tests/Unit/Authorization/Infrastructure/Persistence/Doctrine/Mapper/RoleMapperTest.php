<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Infrastructure\Persistence\Doctrine\Mapper;

use Authorization\Domain\Model\Role\Role;
use Authorization\Domain\ValueObject\{RoleId, RoleName};
use Authorization\Infrastructure\Persistence\Doctrine\Mapper\{PermissionMapper, RoleMapper};
use Authorization\Infrastructure\Persistence\Doctrine\Record\{PermissionRecord, RoleRecord};
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\TenantId;

/**
 * Test RoleMapperTest.
 *
 * @category Mapper Tests
 */
#[CoversClass(className: RoleMapper::class)]
final class RoleMapperTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testToDomainMapsRecord(): void
  {
    $permissionRecord = new PermissionRecord();
    $permissionRecord->id = '123e4567-e89b-12d3-a456-426614174000';
    $permissionRecord->name = 'users.read';
    $permissionRecord->description = 'Read users';
    $permissionRecord->createdAt = new DateTimeImmutable('2024-01-01 00:00:00');

    $record = new RoleRecord();
    $record->id = '223e4567-e89b-12d3-a456-426614174000';
    $record->name = 'admin';
    $record->description = 'Admin role';
    $record->isSystem = true;
    $record->tenantId = '323e4567-e89b-12d3-a456-426614174000';
    $record->createdAt = new DateTimeImmutable('2024-01-01 00:00:00');
    $record->updatedAt = new DateTimeImmutable('2024-01-02 00:00:00');
    $record->permissions->add($permissionRecord);

    $mapper = new RoleMapper(new PermissionMapper());
    $role = $mapper->toDomain($record);

    self::assertSame($record->id, $role->id()->value);
    self::assertSame($record->name, $role->name()->value);
    self::assertSame($record->description, $role->description());
    self::assertSame($record->isSystem, $role->isSystem());
    self::assertSame((string) TenantId::fromString($record->tenantId), (string) $role->tenantId());
    self::assertCount(1, $role->permissions());
  }

  #[Test]
  public function testToRecordMapsRole(): void
  {
    $createdAt = new DateTimeImmutable('2024-01-01 00:00:00');
    $updatedAt = new DateTimeImmutable('2024-01-02 00:00:00');
    $tenantId = TenantId::fromString('323e4567-e89b-12d3-a456-426614174000');

    $role = Role::reconstitute(
      id: new RoleId('223e4567-e89b-12d3-a456-426614174000'),
      name: new RoleName('admin'),
      description: 'Admin role',
      isSystem: true,
      tenantId: $tenantId,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      permissions: [],
    );

    $mapper = new RoleMapper(new PermissionMapper());
    $record = $mapper->toRecord($role);

    self::assertSame($role->id()->value, $record->id);
    self::assertSame($role->name()->value, $record->name);
    self::assertSame($role->description(), $record->description);
    self::assertSame($role->isSystem(), $record->isSystem);
    self::assertSame((string) $tenantId, $record->tenantId);
    self::assertEquals($createdAt, $record->createdAt);
    self::assertEquals($updatedAt, $record->updatedAt);
  }
  // #endregion
}
