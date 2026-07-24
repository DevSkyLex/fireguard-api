<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\Event\Role;

use DateTimeImmutable;
use Organization\Domain\Event\Role\OrganizationRoleAssignedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OrganizationRoleAssignedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationRoleAssignedEvent::class)]
final class OrganizationRoleAssignedEventTest extends TestCase
{
  #[Test]
  public function testExposesPayload(): void
  {
    $event = new OrganizationRoleAssignedEvent('org-1', 'member-1', 'role-1', 'site_manager');

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('member-1', $event->memberId);
    self::assertSame('role-1', $event->roleId);
    self::assertSame('site_manager', $event->roleName);
    self::assertInstanceOf(DateTimeImmutable::class, $event->occurredAt);
  }
}
