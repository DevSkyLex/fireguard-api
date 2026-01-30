<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Application\UseCase\Command\RecordAuditEvent;

use Audit\Application\UseCase\Command\RecordAuditEvent\RecordAuditEventResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test RecordAuditEventResultTest.
 *
 * @category Result Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RecordAuditEventResult::class)]
final class RecordAuditEventResultTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testResultContainsEventId(): void
  {
    $result = new RecordAuditEventResult(eventId: 'event-123');

    self::assertSame('event-123', $result->eventId);
  }
  // #endregion
}
