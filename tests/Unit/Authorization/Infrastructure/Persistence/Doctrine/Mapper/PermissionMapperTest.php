<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Infrastructure\Persistence\Doctrine\Mapper;

use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\ValueObject\{PermissionId, PermissionName};
use Authorization\Infrastructure\Persistence\Doctrine\Mapper\PermissionMapper;
use Authorization\Infrastructure\Persistence\Doctrine\Record\PermissionRecord;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test PermissionMapperTest.
 *
 * @category Mapper Tests
 */
#[CoversClass(className: PermissionMapper::class)]
final class PermissionMapperTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testToDomainMapsRecord(): void
  {
    $record = new PermissionRecord();
    $record->id = '123e4567-e89b-12d3-a456-426614174000';
    $record->name = 'users.read';
    $record->description = 'Read users';
    $record->createdAt = new DateTimeImmutable('2024-01-01 00:00:00');

    $mapper = new PermissionMapper();
    $permission = $mapper->toDomain($record);

    self::assertSame($record->id, $permission->id()->value);
    self::assertSame($record->name, $permission->name()->value);
    self::assertSame($record->description, $permission->description());
  }

  #[Test]
  public function testToRecordMapsPermission(): void
  {
    $createdAt = new DateTimeImmutable('2024-01-02 00:00:00');
    $permission = new Permission(
      id: new PermissionId('123e4567-e89b-12d3-a456-426614174000'),
      name: new PermissionName('users.read'),
      description: 'Read users',
      createdAt: $createdAt,
    );

    $mapper = new PermissionMapper();
    $record = $mapper->toRecord($permission);

    self::assertSame($permission->id()->value, $record->id);
    self::assertSame($permission->name()->value, $record->name);
    self::assertSame($permission->description(), $record->description);
    self::assertEquals($createdAt, $record->createdAt);
  }
  // #endregion
}
