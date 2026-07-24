<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Domain\Event\Request;

use Approval\Domain\Event\Request\ApprovalExpiredEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ApprovalExpiredEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApprovalExpiredEvent::class)]
final class ApprovalExpiredEventTest extends TestCase
{
  #[Test]
  public function testExposesPayloadAccessors(): void
  {
    $event = new ApprovalExpiredEvent(
      organizationId: 'org-1',
      requestId: 'req-1',
      actionType: 'nc_waiver',
      subjectId: 'nc-1',
    );

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('req-1', $event->requestId);
    self::assertSame('nc_waiver', $event->actionType);
    self::assertSame('nc-1', $event->subjectId);
    self::assertInstanceOf(DateTimeImmutable::class, $event->occurredAt);
  }
}
