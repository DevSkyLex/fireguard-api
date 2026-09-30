<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\UseCase\Query\Presence\GetPresencePreferenceSubscription;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use User\Application\Contract\Presence\PresencePreferenceSubscription;
use User\Application\Port\Outbound\PresencePreferenceRealtimePort;
use User\Application\UseCase\Query\Presence\GetPresencePreferenceSubscription\{GetPresencePreferenceSubscriptionHandler, GetPresencePreferenceSubscriptionQuery};

/**
 * Test GetPresencePreferenceSubscriptionHandlerTest.
 *
 * @category Test
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GetPresencePreferenceSubscriptionHandlerTest extends TestCase
{
  #[Test]
  /**
   * @since 1.0.0
   */
  public function issuesOnlyTheAuthorizedSubscription(): void
  {
    $realtime = $this->createMock(PresencePreferenceRealtimePort::class);
    $realtime->expects(self::once())->method('subscribe')->with('user')->willReturn(new PresencePreferenceSubscription('/users/user/presence-preference', 'token', '2026-09-26T00:15:00+00:00'));
    $result = new GetPresencePreferenceSubscriptionHandler($realtime)(new GetPresencePreferenceSubscriptionQuery('user'));
    self::assertSame('/users/user/presence-preference', $result->topic);
    self::assertSame('token', $result->token);
    self::assertSame('2026-09-26T00:15:00+00:00', $result->expiresAt);
  }
}
