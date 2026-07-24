<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Domain\Event;

use DateTimeImmutable;
use Import\Domain\Event\ImportJobFailedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ImportJobFailedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ImportJobFailedEvent::class)]
final class ImportJobFailedEventTest extends TestCase
{
  #[Test]
  public function itExposesItsPayload(): void
  {
    $event = new ImportJobFailedEvent(
      importJobId: 'job-1',
      organizationId: 'org-1',
      kind: 'equipment',
      jobError: 'CSV header is unreadable.',
      createdBy: 'user-1',
    );

    self::assertSame('job-1', $event->importJobId);
    self::assertSame('org-1', $event->organizationId);
    self::assertSame('equipment', $event->kind);
    self::assertSame('CSV header is unreadable.', $event->jobError);
    self::assertSame('user-1', $event->createdBy);
  }

  #[Test]
  public function itStampsOccurredAtOnConstruction(): void
  {
    $before = new DateTimeImmutable();
    $event = new ImportJobFailedEvent('job-1', 'org-1', 'facility', 'boom', 'user-1');
    $after = new DateTimeImmutable();

    self::assertGreaterThanOrEqual($before, $event->occurredAt);
    self::assertLessThanOrEqual($after, $event->occurredAt);
  }
}
