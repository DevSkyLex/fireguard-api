<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\Event\Member;

use DateTimeImmutable;
use Organization\Domain\Event\Member\OrganizationMemberAddedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OrganizationMemberAddedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationMemberAddedEvent::class)]
final class OrganizationMemberAddedEventTest extends TestCase
{
  #[Test]
  public function testExposesPayload(): void
  {
    $event = new OrganizationMemberAddedEvent('org-1', 'member-1', 'user-1', ['role-1', 'role-2']);

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('member-1', $event->memberId);
    self::assertSame('user-1', $event->userId);
    self::assertSame(['role-1', 'role-2'], $event->roleIds);
    self::assertInstanceOf(DateTimeImmutable::class, $event->occurredAt);
  }

  #[Test]
  public function testRoleIdsDefaultToEmpty(): void
  {
    $event = new OrganizationMemberAddedEvent('org-1', 'member-1', 'user-1');

    self::assertSame([], $event->roleIds);
  }
}
