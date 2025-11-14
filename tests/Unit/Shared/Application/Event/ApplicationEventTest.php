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

    $event = new DummyDomainEvent(
      eventId: new Uuid('123e4567-e89b-12d3-a456-426614174000'),
      aggregateId: 'aggregate-1',
      aggregateType: 'dummy',
      payload: ['foo' => 'bar'],
      occurredAt: $occurredAt,
    );

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

/**
 * Test DummyDomainEvent
 *
 * Dummy domain event dedicated to
 * ApplicationEvent tests.
 *
 * @category Application Event Test
 * @package Tests\Shared\Application\Event
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DummyDomainEvent implements DomainEvent
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the domain event.
   *
   * @access public
   *
   * @param Uuid $eventId The event id.
   * @param string $aggregateId The aggregate id.
   * @param string $aggregateType The aggregate type.
   * @param array<string, mixed> $payload The payload.
   * @param DateTimeImmutable $occurredAt The occurred at.
   *
   */
  public function __construct(
    private readonly Uuid $eventId,
    private readonly string $aggregateId,
    private readonly string $aggregateType,
    private readonly array $payload,
    private readonly DateTimeImmutable $occurredAt,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method eventId
   *
   * Get the event id.
   *
   * @access public
   *
   * @return Uuid The event id.
   */
  public function eventId(): Uuid
  {
    return $this->eventId;
  }

  /**
   * Method occurredAt
   *
   * Get the occurred at.
   *
   * @access public
   *
   * @return DateTimeImmutable The occurred at.
   */
  public function occurredAt(): DateTimeImmutable
  {
    return $this->occurredAt;
  }

  /**
   * Method aggregateId
   *
   * Get the aggregate id.
   *
   * @access public
   *
   * @return string The aggregate id.
   */
  public function aggregateId(): string
  {
    return $this->aggregateId;
  }

  /**
   * Method aggregateType
   *
   * Get the aggregate type.
   *
   * @access public
   *
   * @return string The aggregate type.
   */
  public function aggregateType(): string
  {
    return $this->aggregateType;
  }

  /**
   * Method payload
   *
   * Get the payload.
   *
   * @access public
   *
   * @return array<string, mixed> The payload.
   */
  public function payload(): array
  {
    return $this->payload;
  }
  //#endregion
}
