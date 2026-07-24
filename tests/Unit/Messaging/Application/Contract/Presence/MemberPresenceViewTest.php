<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\Contract\Presence;

use Messaging\Application\Contract\Presence\MemberPresenceView;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MemberPresenceView.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MemberPresenceView::class)]
final class MemberPresenceViewTest extends TestCase
{
  #[Test]
  public function itRoundTripsAnOnlineMember(): void
  {
    $view = new MemberPresenceView('member-1', true, '2026-07-24T12:00:00+00:00');

    self::assertSame('member-1', $view->memberId);
    self::assertTrue($view->online);
    self::assertSame('2026-07-24T12:00:00+00:00', $view->lastSeenAt);
  }

  #[Test]
  public function itRoundTripsAnOfflineMemberWithNoTimestamp(): void
  {
    $view = new MemberPresenceView('member-2', false, null);

    self::assertFalse($view->online);
    self::assertNull($view->lastSeenAt);
  }
}
