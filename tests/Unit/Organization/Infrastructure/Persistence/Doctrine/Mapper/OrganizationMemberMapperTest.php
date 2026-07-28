<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use LogicException;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use Organization\Infrastructure\Persistence\Doctrine\Mapper\OrganizationMemberMapper;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationRecord};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OrganizationMemberMapper.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationMemberMapper::class)]
final class OrganizationMemberMapperTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655441810';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655441811';

  #[Test]
  public function testToDomainRebuildsTheMembership(): void
  {
    $joinedAt = new DateTimeImmutable('2026-01-01T09:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;

    $record = new OrganizationMemberRecord();
    $record->id = self::MEMBER_ID;
    $record->organization = $organization;
    $record->userId = 'user-1';
    $record->isActive = false;
    $record->joinedAt = $joinedAt;

    $member = OrganizationMemberMapper::toDomain($record);

    self::assertSame(self::MEMBER_ID, (string) $member->id());
    self::assertSame(self::ORGANIZATION_ID, (string) $member->organizationId());
    self::assertSame('user-1', $member->userId());
    self::assertFalse($member->isActive());
    self::assertEquals($joinedAt, $member->joinedAt());
  }

  #[Test]
  public function testToDomainRejectsADetachedRecord(): void
  {
    $record = new OrganizationMemberRecord();
    $record->id = self::MEMBER_ID;
    $record->userId = 'user-1';
    $record->joinedAt = new DateTimeImmutable('2026-01-01T09:00:00+00:00');

    $this->expectException(LogicException::class);

    OrganizationMemberMapper::toDomain($record);
  }

  #[Test]
  public function testToRecordCopiesTheAggregateState(): void
  {
    $joinedAt = new DateTimeImmutable('2026-01-01T09:00:00+00:00');

    $record = OrganizationMemberMapper::toRecord(OrganizationMember::reconstitute(
      id: OrganizationMemberId::fromString(self::MEMBER_ID),
      organizationId: OrganizationId::fromString(self::ORGANIZATION_ID),
      userId: 'user-1',
      isActive: true,
      joinedAt: $joinedAt,
    ));

    self::assertSame(self::MEMBER_ID, $record->id);
    self::assertSame('user-1', $record->userId);
    self::assertTrue($record->isActive);
    self::assertEquals($joinedAt, $record->joinedAt);
    self::assertNull($record->organization);
  }
}
