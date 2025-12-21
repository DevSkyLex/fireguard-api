<?php

declare(strict_types=1);

namespace Tests\Shared\Application\Event;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Event\ApplicationEvent;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\ValueObject\Uuid;

/**
 * Test ApplicationEventTest.
 *
 * Test the ApplicationEvent class.
 *
 * @category Application Event Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ApplicationEvent::class)]
final class ApplicationEventTest extends TestCase
{
  // #region Methods
  /**
   * Method fromDomain.
   *
   * Test that the fromDomain method copies
   * all metadata from the domain event.
   *
   * @return void no return value
   */
  #[Test]
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
      actual: (string) $applicationEvent->eventId,
    );
    self::assertSame(
      expected: 'aggregate-1',
      actual: $applicationEvent->aggregateId,
    );
    self::assertSame(
      expected: 'dummy',
      actual: $applicationEvent->aggregateType,
    );
    self::assertSame(
      expected: ['foo' => 'bar'],
      actual: $applicationEvent->payload,
    );
    self::assertSame(
      expected: $occurredAt,
      actual: $applicationEvent->occurredAt,
    );
  }
  // #endregion
}

/**
 * Test DummyDomainEvent.
 *
 * Dummy domain event dedicated to
 * ApplicationEvent tests.
 *
 * @category Application Event Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DummyDomainEvent implements DomainEvent
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize the domain event.
   *
   * @param Uuid $eventId the event id
   * @param string $aggregateId the aggregate id
   * @param string $aggregateType the aggregate type
   * @param array<string, mixed> $payload the payload
   * @param DateTimeImmutable $occurredAt the occurred at
   */
  public function __construct(
    private readonly Uuid $eventId,
    private readonly string $aggregateId,
    private readonly string $aggregateType,
    private readonly array $payload,
    private readonly DateTimeImmutable $occurredAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method eventId.
   *
   * Get the event id.
   *
   * @return Uuid the event id
   */
  public function eventId(): Uuid
  {
    return $this->eventId;
  }

  /**
   * Method occurredAt.
   *
   * Get the occurred at.
   *
   * @return DateTimeImmutable the occurred at
   */
  public function occurredAt(): DateTimeImmutable
  {
    return $this->occurredAt;
  }

  /**
   * Method aggregateId.
   *
   * Get the aggregate id.
   *
   * @return string the aggregate id
   */
  public function aggregateId(): string
  {
    return $this->aggregateId;
  }

  /**
   * Method aggregateType.
   *
   * Get the aggregate type.
   *
   * @return string the aggregate type
   */
  public function aggregateType(): string
  {
    return $this->aggregateType;
  }

  /**
   * Method payload.
   *
   * Get the payload.
   *
   * @return array<string, mixed> the payload
   */
  public function payload(): array
  {
    return $this->payload;
  }
  // #endregion
}
