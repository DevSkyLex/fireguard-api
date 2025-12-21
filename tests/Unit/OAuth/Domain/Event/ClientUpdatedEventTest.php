<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\Event;

use DateTimeImmutable;
use OAuth\Domain\Event\ClientUpdatedEvent;
use OAuth\Domain\ValueObject\ClientId;
use OAuth\Domain\ValueObject\ClientName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Uuid;

/**
 * Test ClientUpdatedEventTest.
 *
 * @category Event Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ClientUpdatedEvent::class)]
final class ClientUpdatedEventTest extends TestCase
{
  // #region Methods
  /**
   * Method testEventIsCreatedWithAllProperties.
   *
   * Test that the event is created
   * with all required properties
   *
   * @return void No return value
   */
  #[Test]
  public function testEventIsCreatedWithAllProperties(): void
  {
    $clientId = new ClientId(value: '123e4567-e89b-12d3-a456-426614174000');
    $clientName = new ClientName(value: 'Updated Client');
    $occurredAt = new DateTimeImmutable();

    $event = new ClientUpdatedEvent(
      eventId: new Uuid('550e8400-e29b-41d4-a716-446655440001'),
      clientId: $clientId,
      name: $clientName,
      occurredAt: $occurredAt
    );

    self::assertSame(expected: $clientId, actual: $event->clientId);
    self::assertSame(expected: $clientName, actual: $event->name);
    self::assertSame(expected: $occurredAt, actual: $event->occurredAt());
  }

  /**
   * Method testEventIdIsAutomaticallyGenerated.
   *
   * Test that the event ID is
   * automatically generated
   *
   * @return void No return value
   */
  #[Test]
  public function testEventIdIsProvided(): void
  {
    $eventId = new Uuid('550e8400-e29b-41d4-a716-446655440002');
    $event = new ClientUpdatedEvent(
      eventId: $eventId,
      clientId: new ClientId(value: '123e4567-e89b-12d3-a456-426614174000'),
      name: new ClientName(value: 'Updated Client'),
      occurredAt: new DateTimeImmutable()
    );

    self::assertSame(expected: $eventId, actual: $event->eventId());
  }

  /**
   * Method testAggregateIdReturnsClientId.
   *
   * Test that aggregateId returns
   * the client ID value
   *
   * @return void No return value
   */
  #[Test]
  public function testAggregateIdReturnsClientId(): void
  {
    $clientId = '123e4567-e89b-12d3-a456-426614174000';
    $event = new ClientUpdatedEvent(
      eventId: new Uuid('550e8400-e29b-41d4-a716-446655440003'),
      clientId: new ClientId(value: $clientId),
      name: new ClientName(value: 'Updated Client'),
      occurredAt: new DateTimeImmutable()
    );

    self::assertSame(expected: $clientId, actual: $event->aggregateId());
  }

  /**
   * Method testAggregateTypeReturnsClient.
   *
   * Test that aggregateType returns
   * 'client'
   *
   * @return void No return value
   */
  #[Test]
  public function testAggregateTypeReturnsClient(): void
  {
    $event = new ClientUpdatedEvent(
      eventId: new Uuid('550e8400-e29b-41d4-a716-446655440004'),
      clientId: new ClientId(value: '123e4567-e89b-12d3-a456-426614174000'),
      name: new ClientName(value: 'Updated Client'),
      occurredAt: new DateTimeImmutable()
    );

    self::assertSame(expected: 'client', actual: $event->aggregateType());
  }

  /**
   * Method testPayloadContainsEventData.
   *
   * Test that payload contains
   * the event data
   *
   * @return void No return value
   */
  #[Test]
  public function testPayloadContainsEventData(): void
  {
    $clientId = '123e4567-e89b-12d3-a456-426614174000';
    $clientName = 'Updated Client';

    $event = new ClientUpdatedEvent(
      eventId: new Uuid('550e8400-e29b-41d4-a716-446655440005'),
      clientId: new ClientId(value: $clientId),
      name: new ClientName(value: $clientName),
      occurredAt: new DateTimeImmutable()
    );

    $payload = $event->payload();

    self::assertArrayHasKey(key: 'client_id', array: $payload);
    self::assertArrayHasKey(key: 'name', array: $payload);
    self::assertSame(expected: $clientId, actual: $payload['client_id']);
    self::assertSame(expected: $clientName, actual: $payload['name']);
  }
  // #endregion
}
