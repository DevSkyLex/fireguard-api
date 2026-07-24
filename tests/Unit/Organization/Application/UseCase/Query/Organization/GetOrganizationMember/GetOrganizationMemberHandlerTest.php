<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\GetOrganizationMember;

use DateTimeImmutable;
use DateTimeZone;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Application\UseCase\Query\Organization\GetOrganizationMember\{
  GetOrganizationMemberHandler,
  GetOrganizationMemberQuery
};
use Organization\Domain\Exception\OrganizationMemberNotFoundException;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * Test GetOrganizationMemberHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GetOrganizationMemberHandlerTest extends TestCase
{
  private const string MEMBER_ID = '11111111-1111-4111-8111-111111111111';

  private const string ORGANIZATION_ID = '22222222-2222-4222-8222-222222222222';

  private const string USER_ID = '33333333-3333-4333-8333-333333333333';

  #[Test]
  public function itReturnsTheResolvedMember(): void
  {
    $joinedAt = new DateTimeImmutable('2026-01-15T09:30:00', new DateTimeZone('UTC'));

    $member = OrganizationMember::reconstitute(
      OrganizationMemberId::fromString(self::MEMBER_ID),
      OrganizationId::fromString(self::ORGANIZATION_ID),
      self::USER_ID,
      true,
      $joinedAt,
    );

    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($member);

    $handler = new GetOrganizationMemberHandler($members);

    $result = $handler(new GetOrganizationMemberQuery(self::MEMBER_ID));

    self::assertSame(self::MEMBER_ID, $result->id);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
    self::assertSame(self::USER_ID, $result->userId);
    self::assertTrue($result->isActive);
    self::assertSame($joinedAt, $result->joinedAt);
  }

  #[Test]
  public function itReflectsAnInactiveMembership(): void
  {
    $member = OrganizationMember::reconstitute(
      OrganizationMemberId::fromString(self::MEMBER_ID),
      OrganizationId::fromString(self::ORGANIZATION_ID),
      self::USER_ID,
      false,
      new DateTimeImmutable('2026-01-15T09:30:00', new DateTimeZone('UTC')),
    );

    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($member);

    $handler = new GetOrganizationMemberHandler($members);

    $result = $handler(new GetOrganizationMemberQuery(self::MEMBER_ID));

    self::assertFalse($result->isActive);
  }

  #[Test]
  public function itThrowsWhenTheMemberDoesNotExist(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn(null);

    $handler = new GetOrganizationMemberHandler($members);

    $this->expectException(OrganizationMemberNotFoundException::class);
    $this->expectExceptionMessage(sprintf('Organization member with ID "%s" not found.', self::MEMBER_ID));

    $handler(new GetOrganizationMemberQuery(self::MEMBER_ID));
  }
}
