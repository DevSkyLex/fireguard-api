<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Application\Service;

use Calendar\Application\Contract\Feed\{CalendarFeedItem, CalendarFeedSourceState};
use Calendar\Application\Port\Outbound\Feed\CalendarFeedSourcePort;
use Calendar\Application\Service\{CalendarFeedAggregator, CalendarFeedSourceRegistry};
use DateTimeImmutable;
use Generator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\LoggerPort;

use function array_keys;

/**
 * Class CalendarFeedSourceRegistryTest
 *
 * Exercises construction and aggregation with independent contributors instead of module-specific ports.
 *
 * @category Service Tests
 */
#[CoversClass(CalendarFeedSourceRegistry::class)]
#[CoversClass(CalendarFeedAggregator::class)]
final class CalendarFeedSourceRegistryTest extends TestCase
{
  #[Test]
  public function itAcceptsAnEmptyCatalog(): void
  {
    $registry = new CalendarFeedSourceRegistry([]);

    self::assertSame([], $registry->all());
  }

  #[Test]
  public function itMaterializesAnIterableOnceAndPreservesContributorOrder(): void
  {
    $maintenance = $this->contributor('maintenance');
    $events = $this->contributor('calendar_event');
    $inspections = $this->contributor('inspection');
    $visits = 0;
    $sources = (static function () use ($maintenance, $events, $inspections, &$visits): Generator {
      ++$visits;

      yield $maintenance;
      yield $events;
      yield $inspections;
    })();
    $registry = new CalendarFeedSourceRegistry($sources);

    self::assertSame(['maintenance', 'calendar_event', 'inspection'], array_keys($registry->all()));
    self::assertSame($events, $registry->all()['calendar_event']);
    self::assertSame(1, $visits);
  }

  #[Test]
  public function itRejectsDuplicateSourceKeysAtConstruction(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Duplicate calendar feed source key "inspection".');

    new CalendarFeedSourceRegistry([$this->contributor('inspection'), $this->contributor('inspection')]);
  }

  #[Test]
  public function itContributesANewSourceWithoutChangingTheAggregator(): void
  {
    $item = new CalendarFeedItem('review', 'review-1', 'Review', null, new DateTimeImmutable('2026-08-15'), null, false, null, 'open', 'review', 'review-1');
    $registry = new CalendarFeedSourceRegistry([$this->contributor('review', [$item])]);
    $aggregator = new CalendarFeedAggregator($registry, $this->createStub(LoggerPort::class));

    $result = $aggregator->aggregate('organization-1', new DateTimeImmutable('2026-08-01'), new DateTimeImmutable('2026-08-31'), ['review']);

    self::assertSame([$item], $result->items);
    self::assertEquals([new CalendarFeedSourceState('review', true, false)], $result->sources);
    self::assertTrue($result->isComplete());
  }

  /**
   * @param list<CalendarFeedItem> $items source items
   */
  private function contributor(string $key, array $items = []): CalendarFeedSourcePort
  {
    return new class ($key, $items) implements CalendarFeedSourcePort {
      /**
       * @param list<CalendarFeedItem> $items source items
       */
      public function __construct(private string $key, private array $items)
      {
      }

      public function sourceKey(): string
      {
        return $this->key;
      }

      public function findBetween(string $organizationId, DateTimeImmutable $from, DateTimeImmutable $to, int $limit): array
      {
        return $this->items;
      }
    };
  }
}
