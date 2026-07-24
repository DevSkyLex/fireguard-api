<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Domain\Event;

use DateTimeImmutable;
use Import\Domain\Event\ImportJobCompletedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ImportJobCompletedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ImportJobCompletedEvent::class)]
final class ImportJobCompletedEventTest extends TestCase
{
  #[Test]
  public function itExposesItsPayload(): void
  {
    $event = new ImportJobCompletedEvent(
      importJobId: 'job-1',
      organizationId: 'org-1',
      kind: 'equipment',
      totalRows: 10,
      successfulRows: 8,
      failedRows: 2,
      createdBy: 'user-1',
    );

    self::assertSame('job-1', $event->importJobId);
    self::assertSame('org-1', $event->organizationId);
    self::assertSame('equipment', $event->kind);
    self::assertSame(10, $event->totalRows);
    self::assertSame(8, $event->successfulRows);
    self::assertSame(2, $event->failedRows);
    self::assertSame('user-1', $event->createdBy);
  }

  #[Test]
  public function itStampsOccurredAtOnConstruction(): void
  {
    $before = new DateTimeImmutable();
    $event = new ImportJobCompletedEvent('job-1', 'org-1', 'facility', 0, 0, 0, 'user-1');
    $after = new DateTimeImmutable();

    self::assertGreaterThanOrEqual($before, $event->occurredAt);
    self::assertLessThanOrEqual($after, $event->occurredAt);
  }
}
