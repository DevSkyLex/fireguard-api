<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Domain\Event\Request;

use Approval\Domain\Event\Request\ApprovalExecutionFailedEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ApprovalExecutionFailedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApprovalExecutionFailedEvent::class)]
final class ApprovalExecutionFailedEventTest extends TestCase
{
  #[Test]
  public function testExposesPayloadAccessors(): void
  {
    $event = new ApprovalExecutionFailedEvent(
      organizationId: 'org-1',
      requestId: 'req-1',
      actionType: 'equipment_decommission',
      subjectId: 'equip-1',
      error: 'subject changed',
      decisionByUserId: 'user-1',
    );

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('req-1', $event->requestId);
    self::assertSame('equipment_decommission', $event->actionType);
    self::assertSame('equip-1', $event->subjectId);
    self::assertSame('subject changed', $event->error);
    self::assertSame('user-1', $event->decisionByUserId);
    self::assertInstanceOf(DateTimeImmutable::class, $event->occurredAt);
  }
}
