<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Contract\Team;

use Organization\Application\Contract\Team\TeamMembershipSnapshot;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test TeamMembershipSnapshot.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TeamMembershipSnapshot::class)]
final class TeamMembershipSnapshotTest extends TestCase
{
  #[Test]
  public function testExposesReadonlyProperties(): void
  {
    $snapshot = new TeamMembershipSnapshot('team-1', 'Alpha Team', ['member-1', 'member-2']);

    self::assertSame('team-1', $snapshot->teamId);
    self::assertSame('Alpha Team', $snapshot->name);
    self::assertSame(['member-1', 'member-2'], $snapshot->memberIds);
  }

  #[Test]
  public function testSupportsEmptyMembership(): void
  {
    $snapshot = new TeamMembershipSnapshot('team-2', 'Empty Team', []);

    self::assertSame([], $snapshot->memberIds);
  }
}
