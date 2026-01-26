<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Event;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\AuditEvent;
use Shared\Domain\ValueObject\Uuid;

/**
 * Test AuditEventTest.
 *
 * @category Event Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: AuditEvent::class)]
final class AuditEventTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testLoginSuccessFactoryBuildsEvent(): void
  {
    $event = AuditEvent::loginSuccess(
      eventId: new Uuid('123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-123',
      ipAddress: '127.0.0.1',
      userAgent: 'test-agent',
    );

    self::assertSame(AuditEvent::ACTION_LOGIN_SUCCESS, $event->action());
    self::assertSame('user-123', $event->userId());
    self::assertSame('127.0.0.1', $event->ipAddress());
    self::assertSame('test-agent', $event->userAgent());
    self::assertSame('user-123', $event->aggregateId());
  }

  #[Test]
  public function testLoginFailedFactoryBuildsPayload(): void
  {
    $event = AuditEvent::loginFailed(
      eventId: new Uuid('123e4567-e89b-12d3-a456-426614174000'),
      attemptedUsername: 'user@example.com',
      ipAddress: '127.0.0.1',
      userAgent: 'test-agent',
      reason: 'invalid_credentials',
    );

    /** @var array{action: string, metadata: array{attempted_username: string, failure_reason: string}} $payload */
    $payload = $event->payload();
    self::assertSame(AuditEvent::ACTION_LOGIN_FAILED, $payload['action']);
    self::assertSame('user@example.com', $payload['metadata']['attempted_username']);
    self::assertSame('invalid_credentials', $payload['metadata']['failure_reason']);
    self::assertSame('system', $event->aggregateId());
  }

  #[Test]
  public function testTokenIssuedFactoryBuildsEvent(): void
  {
    $event = AuditEvent::tokenIssued(
      eventId: new Uuid('123e4567-e89b-12d3-a456-426614174000'),
      clientId: 'client-123',
      userId: null,
      grantType: 'client_credentials',
      ipAddress: '127.0.0.1',
    );

    self::assertSame(AuditEvent::ACTION_TOKEN_ISSUED, $event->action());
    self::assertSame('client-123', $event->clientId());
    self::assertSame('client-123', $event->aggregateId());
    self::assertInstanceOf(DateTimeImmutable::class, $event->occurredAt());
  }
  // #endregion
}
