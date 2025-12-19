<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\Event;

use DateTimeImmutable;
use OAuth\Domain\Event\TokenRevokedEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Class TokenRevokedEventTest.
 *
 * Unit tests for the TokenRevokedEvent.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \OAuth\Domain\Event\TokenRevokedEvent
 */
#[CoversClass(className: TokenRevokedEvent::class)]
final class TokenRevokedEventTest extends TestCase
{
    // #region Methods
    /**
     * Method testCanBeCreated.
     */
    #[Test]
    public function testCanBeCreated(): void
    {
        $event = new TokenRevokedEvent(
            tokenId: 'token-123',
            tokenType: 'access_token',
            reason: 'user_logout',
        );

        $this->assertEquals(expected: 'token-123', actual: $event->tokenId);
        $this->assertEquals(expected: 'access_token', actual: $event->tokenType);
        $this->assertEquals(expected: 'user_logout', actual: $event->reason);
        $this->assertInstanceOf(expected: DateTimeImmutable::class, actual: $event->occurredAt);
    }

    /**
     * Method testCanBeCreatedWithNullReason.
     */
    #[Test]
    public function testCanBeCreatedWithNullReason(): void
    {
        $event = new TokenRevokedEvent(
            tokenId: 'token-123',
            tokenType: 'refresh_token',
            reason: null,
        );

        $this->assertNull(actual: $event->reason);
        $this->assertEquals(expected: 'refresh_token', actual: $event->tokenType);
    }

    /**
     * Method testOccurredAtIsSetAutomatically.
     */
    #[Test]
    public function testOccurredAtIsSetAutomatically(): void
    {
        $before = new DateTimeImmutable();
        $event = new TokenRevokedEvent(
            tokenId: 'token-123',
            tokenType: 'access_token',
        );
        $after = new DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $event->occurredAt);
        $this->assertLessThanOrEqual($after, $event->occurredAt);
    }
    // #endregion
}
