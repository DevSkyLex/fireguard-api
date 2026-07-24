<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Domain\Event\Request;

use Approval\Domain\Event\Request\ApprovalRequestedEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ApprovalRequestedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApprovalRequestedEvent::class)]
final class ApprovalRequestedEventTest extends TestCase
{
  #[Test]
  public function testExposesPayloadAccessors(): void
  {
    $event = new ApprovalRequestedEvent(
      organizationId: 'org-1',
      requestId: 'req-1',
      actionType: 'nc_waiver',
      subjectId: 'nc-1',
      requestedByMemberId: 'member-1',
      requestedByUserId: 'user-1',
    );

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('req-1', $event->requestId);
    self::assertSame('nc_waiver', $event->actionType);
    self::assertSame('nc-1', $event->subjectId);
    self::assertSame('member-1', $event->requestedByMemberId);
    self::assertSame('user-1', $event->requestedByUserId);
    self::assertInstanceOf(DateTimeImmutable::class, $event->occurredAt);
  }
}
