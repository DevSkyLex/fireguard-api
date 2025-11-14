<?php

declare(strict_types=1);

namespace Tests\Shared\Application\Event;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Shared\Application\Event\ApplicationEvent;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\ValueObject\Uuid;

/**
 * Test ApplicationEventTest
 *
 * Test the ApplicationEvent class.
 *
 * @category Application Event Test
 * @package Tests\Shared\Application\Event
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApplicationEventTest extends TestCase
{
  //#region Methods
  /**
   * Method fromDomain
   * @method testFromDomainCopiesAllMetadata(): void
   *
   * Test that the fromDomain method copies
   * all metadata from the domain event.
   *
   * @access public
   *
   * @return void No return value.
   */
  public function testFromDomainCopiesAllMetadata(): void
  {
    $occurredAt = new DateTimeImmutable(datetime: '-1 minute');

    $event = new class($occurredAt) implements DomainEvent {
      public function __construct(private DateTimeImmutable $occurredAt) {}
      public function eventId(): Uuid { return new Uuid('123e4567-e89b-12d3-a456-426614174000'); }
      public function occurredAt(): DateTimeImmutable { return $this->occurredAt; }
      public function aggregateId(): string { return 'aggregate-1'; }
      public function aggregateType(): string { return 'dummy'; }
      public function payload(): array { return ['foo' => 'bar']; }
    };

    $applicationEvent = ApplicationEvent::fromDomain(event: $event);

    self::assertSame(
      expected: '123e4567-e89b-12d3-a456-426614174000',
      actual: (string) $applicationEvent->eventId
    );
    self::assertSame(
      expected: 'aggregate-1',
      actual: $applicationEvent->aggregateId
    );
    self::assertSame(
      expected: 'dummy',
      actual: $applicationEvent->aggregateType
    );
    self::assertSame(
      expected: ['foo' => 'bar'],
      actual: $applicationEvent->payload
    );
    self::assertSame(
      expected: $occurredAt,
      actual: $applicationEvent->occurredAt
    );
  }
  //#endregion
}
