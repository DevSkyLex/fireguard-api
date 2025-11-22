<?php

declare(strict_types=1);

namespace Tests\Client\Domain\Event;

use Client\Domain\Event\ClientDeactivatedEvent;
use Client\Domain\ValueObject\ClientId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test ClientDeactivatedEventTest
 * @final
 *
 * Test class for the ClientDeactivatedEvent domain event.
 *
 * @category Event Tests
 * @package Tests\Client\Domain\Event
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ClientDeactivatedEvent::class)]
final class ClientDeactivatedEventTest extends TestCase
{
	//#region Methods
	/**
	 * Method testEventIsCreatedWithAllProperties
	 *
	 * Test that the event is created
	 * with all required properties
	 *
	 * @access public
	 *
	 * @return void No return value
	 */
	#[Test]
	public function testEventIsCreatedWithAllProperties(): void
	{
		$clientId = new ClientId(value: '123e4567-e89b-12d3-a456-426614174000');
		$occurredAt = new DateTimeImmutable();

		$event = new ClientDeactivatedEvent(
			clientId: $clientId,
			occurredAt: $occurredAt
		);

		self::assertSame(expected: $clientId, actual: $event->clientId);
		self::assertSame(expected: $occurredAt, actual: $event->occurredAt());
	}

	/**
	 * Method testEventIdIsAutomaticallyGenerated
	 *
	 * Test that the event ID is
	 * automatically generated
	 *
	 * @access public
	 *
	 * @return void No return value
	 */
	#[Test]
	public function testEventIdIsAutomaticallyGenerated(): void
	{
		$event = new ClientDeactivatedEvent(
			clientId: new ClientId(value: '123e4567-e89b-12d3-a456-426614174000'),
			occurredAt: new DateTimeImmutable()
		);

		self::assertMatchesRegularExpression(
			pattern: '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
			string: $event->eventId()->value
		);
	}

	/**
	 * Method testAggregateIdReturnsClientId
	 *
	 * Test that aggregateId returns
	 * the client ID value
	 *
	 * @access public
	 *
	 * @return void No return value
	 */
	#[Test]
	public function testAggregateIdReturnsClientId(): void
	{
		$clientId = '123e4567-e89b-12d3-a456-426614174000';
		$event = new ClientDeactivatedEvent(
			clientId: new ClientId(value: $clientId),
			occurredAt: new DateTimeImmutable()
		);

		self::assertSame(expected: $clientId, actual: $event->aggregateId());
	}

	/**
	 * Method testAggregateTypeReturnsClient
	 *
	 * Test that aggregateType returns
	 * 'client'
	 *
	 * @access public
	 *
	 * @return void No return value
	 */
	#[Test]
	public function testAggregateTypeReturnsClient(): void
	{
		$event = new ClientDeactivatedEvent(
			clientId: new ClientId(value: '123e4567-e89b-12d3-a456-426614174000'),
			occurredAt: new DateTimeImmutable()
		);

		self::assertSame(expected: 'client', actual: $event->aggregateType());
	}

	/**
	 * Method testPayloadContainsEventData
	 *
	 * Test that payload contains
	 * the event data
	 *
	 * @access public
	 *
	 * @return void No return value
	 */
	#[Test]
	public function testPayloadContainsEventData(): void
	{
		$clientId = '123e4567-e89b-12d3-a456-426614174000';

		$event = new ClientDeactivatedEvent(
			clientId: new ClientId(value: $clientId),
			occurredAt: new DateTimeImmutable()
		);

		$payload = $event->payload();

		self::assertArrayHasKey(key: 'client_id', array: $payload);
		self::assertSame(expected: $clientId, actual: $payload['client_id']);
	}
	//#endregion
}

