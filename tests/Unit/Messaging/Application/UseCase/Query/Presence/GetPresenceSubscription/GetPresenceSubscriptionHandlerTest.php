<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Query\Presence\GetPresenceSubscription;

use Messaging\Application\Contract\Presence\PresenceSubscription;
use Messaging\Application\Port\Outbound\{MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort};
use Messaging\Application\Port\Outbound\PresenceRealtimePort;
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Application\UseCase\Query\Presence\GetPresenceSubscription\{GetPresenceSubscriptionHandler, GetPresenceSubscriptionQuery};
use Messaging\Domain\Exception\MessagingAccessDeniedException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test GetPresenceSubscriptionHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GetPresenceSubscriptionHandlerTest extends TestCase
{
  #[Test]
  /**
   * @since 1.0.0
   */
  public function issuesOnlyTheAuthorizedSubscription(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('member');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);
    $policy = new MessagingAccessPolicy($authorization, $members, $this->createStub(MessagingParticipantRepositoryPort::class));
    $realtime = $this->createMock(PresenceRealtimePort::class);
    $realtime->expects(self::once())->method('subscribe')->with('org')->willReturn(new PresenceSubscription('/organizations/org/presence', 'token', '2026-09-26T00:15:00+00:00'));
    $result = new GetPresenceSubscriptionHandler($realtime, $policy)(new GetPresenceSubscriptionQuery('user', 'org'));
    self::assertSame('/organizations/org/presence', $result->topic);
    self::assertSame('token', $result->token);
    self::assertSame('2026-09-26T00:15:00+00:00', $result->expiresAt);
  }

  #[Test]
  /**
   * @since 1.0.0
   */
  public function deniedMemberNeverReceivesAToken(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('member');
    $policy = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class));
    $realtime = $this->createMock(PresenceRealtimePort::class);
    $realtime->expects(self::never())->method('subscribe');
    $this->expectException(MessagingAccessDeniedException::class);
    new GetPresenceSubscriptionHandler($realtime, $policy)(new GetPresenceSubscriptionQuery('user', 'org'));
  }
}
