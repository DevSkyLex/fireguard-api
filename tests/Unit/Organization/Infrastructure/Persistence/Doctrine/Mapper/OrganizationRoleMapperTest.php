<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use LogicException;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleId, OrganizationRoleName};
use Organization\Infrastructure\Persistence\Doctrine\Mapper\OrganizationRoleMapper;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OrganizationRoleMapper.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationRoleMapper::class)]
final class OrganizationRoleMapperTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655441810';

  private const string ROLE_ID = '550e8400-e29b-41d4-a716-446655441812';

  #[Test]
  public function testToDomainRebuildsTheRole(): void
  {
    $createdAt = new DateTimeImmutable('2026-01-01T09:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;

    $record = new OrganizationRoleRecord();
    $record->id = self::ROLE_ID;
    $record->organization = $organization;
    $record->name = 'manager';
    $record->permissions = ['organization.read'];
    $record->description = 'Manager role';
    $record->isSystem = false;
    $record->createdAt = $createdAt;

    $role = OrganizationRoleMapper::toDomain($record);

    self::assertSame(self::ROLE_ID, (string) $role->id());
    self::assertSame(self::ORGANIZATION_ID, (string) $role->organizationId());
    self::assertSame('manager', (string) $role->name());
    self::assertSame(['organization.read'], $role->permissions());
    self::assertSame('Manager role', $role->description());
    self::assertFalse($role->isSystem());
    self::assertEquals($createdAt, $role->createdAt());
  }

  #[Test]
  public function testToDomainRejectsADetachedRecord(): void
  {
    $record = new OrganizationRoleRecord();
    $record->id = self::ROLE_ID;
    $record->name = 'manager';
    $record->createdAt = new DateTimeImmutable('2026-01-01T09:00:00+00:00');

    $this->expectException(LogicException::class);

    OrganizationRoleMapper::toDomain($record);
  }

  #[Test]
  public function testToRecordCopiesTheAggregateState(): void
  {
    $createdAt = new DateTimeImmutable('2026-01-01T09:00:00+00:00');

    $record = OrganizationRoleMapper::toRecord(OrganizationRole::reconstitute(
      id: OrganizationRoleId::fromString(self::ROLE_ID),
      organizationId: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationRoleName('manager'),
      permissions: ['organization.read'],
      isSystem: true,
      createdAt: $createdAt,
      description: 'Manager role',
    ));

    self::assertSame(self::ROLE_ID, $record->id);
    self::assertSame('manager', $record->name);
    self::assertSame('Manager role', $record->description);
    self::assertTrue($record->isSystem);
    self::assertEquals($createdAt, $record->createdAt);
    self::assertNull($record->organization);
  }
}
