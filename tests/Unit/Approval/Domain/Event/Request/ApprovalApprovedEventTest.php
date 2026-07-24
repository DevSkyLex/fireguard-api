<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Domain\Event\Request;

use Approval\Domain\Event\Request\ApprovalApprovedEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ApprovalApprovedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApprovalApprovedEvent::class)]
final class ApprovalApprovedEventTest extends TestCase
{
  #[Test]
  public function testExposesPayloadAccessors(): void
  {
    $event = new ApprovalApprovedEvent(
      organizationId: 'org-1',
      requestId: 'req-1',
      actionType: 'equipment_decommission',
      subjectId: 'equip-1',
      decisionByMemberId: 'member-1',
      decisionByUserId: 'user-1',
    );

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('req-1', $event->requestId);
    self::assertSame('equipment_decommission', $event->actionType);
    self::assertSame('equip-1', $event->subjectId);
    self::assertSame('member-1', $event->decisionByMemberId);
    self::assertSame('user-1', $event->decisionByUserId);
    self::assertInstanceOf(DateTimeImmutable::class, $event->occurredAt);
  }
}
