<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Trait;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Trait\RecordsDomainEvents;
use Shared\Domain\ValueObject\{Email, Uuid};
use Tests\Helper\TestEventIdProvider;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, Username};

/**
 * Test RecordsDomainEventsTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RecordsDomainEventsTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testRecordReleaseAndClearEvents(): void
  {
    $aggregate = new class () {
      use RecordsDomainEvents;

      public function record(DomainEvent $event): void
      {
        $this->recordEvent($event);
      }
    };

    $event = $this->createEvent();

    self::assertFalse($aggregate->hasRecordedEvents());

    $aggregate->record($event);
    self::assertTrue($aggregate->hasRecordedEvents());

    $events = $aggregate->releaseEvents();
    self::assertCount(1, $events);
    self::assertSame($event, $events[0]);
    self::assertFalse($aggregate->hasRecordedEvents());

    $aggregate->record($event);
    $aggregate->clearRecordedEvents();
    self::assertFalse($aggregate->hasRecordedEvents());
  }

  #[Test]
  public function testAggregateHasAndClearsRecordedEvents(): void
  {
    $eventIdProvider = new TestEventIdProvider();
    $user = User::register(
      id: new UserId('550e8400-e29b-41d4-a716-446655440099'),
      username: new Username('jdoe'),
      email: new Email('jdoe@example.com'),
      password: HashedPassword::fromPlain('password123'),
      profile: new UserProfile('John', 'Doe'),
      eventIdProvider: $eventIdProvider,
    );

    self::assertTrue($user->hasRecordedEvents());

    $user->clearRecordedEvents();

    self::assertFalse($user->hasRecordedEvents());
  }

  private function createEvent(): DomainEvent
  {
    return new class () implements DomainEvent {
      private Uuid $eventId;

      private DateTimeImmutable $occurredAt;

      public function __construct()
      {
        $this->eventId = new Uuid('00000000-0000-4000-a000-000000000003');
        $this->occurredAt = new DateTimeImmutable('2024-01-01T00:00:00+00:00');
      }

      public function eventId(): Uuid
      {
        return $this->eventId;
      }

      public function occurredAt(): DateTimeImmutable
      {
        return $this->occurredAt;
      }

      public function aggregateId(): string
      {
        return 'aggregate-1';
      }

      public function aggregateType(): string
      {
        return 'Aggregate';
      }

      public function payload(): array
      {
        return ['key' => 'value'];
      }
    };
  }
  // #endregion
}
