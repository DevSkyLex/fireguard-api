<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Application\UseCase\Command\RecordAuditEvent;

use Audit\Application\Port\Outbound\AuditEventRepositoryPort;
use Audit\Application\UseCase\Command\RecordAuditEvent\{
  RecordAuditEventCommand,
  RecordAuditEventHandler,
  RecordAuditEventResult
};
use Audit\Domain\Model\AuditEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Domain\ValueObject\Uuid;

/**
 * Test RecordAuditEventHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RecordAuditEventHandler::class)]
final class RecordAuditEventHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokePersistsEventAndReturnsResult(): void
  {
    $eventId = new Uuid('550e8400-e29b-41d4-a716-446655440000');
    $occurredAt = new DateTimeImmutable('2026-01-30T09:45:00+00:00');

    $command = new RecordAuditEventCommand(
      action: 'auth.login_success',
      actorType: 'user',
      actorId: 'user-123',
      actorEmail: 'user@example.com',
      actorEmailHash: 'email-hash',
      subjectType: 'token',
      subjectId: 'token-123',
      clientId: 'client-123',
      tenantId: 'tenant-123',
      organizationId: 'org-123',
      ipAddress: '203.0.113.10',
      ipHash: 'ip-hash',
      userAgent: 'Mozilla',
      metadata: ['reason' => 'success'],
      occurredAt: $occurredAt,
    );

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(Uuid::class)
      ->willReturn($eventId);

    $storedEvent = new AuditEvent(
      id: $eventId,
      action: $command->action,
      actorType: $command->actorType,
      actorId: $command->actorId,
      actorEmail: $command->actorEmail,
      actorEmailHash: $command->actorEmailHash,
      subjectType: $command->subjectType,
      subjectId: $command->subjectId,
      clientId: $command->clientId,
      tenantId: $command->tenantId,
      ipAddress: $command->ipAddress,
      ipHash: $command->ipHash,
      userAgent: $command->userAgent,
      metadata: ['reason' => 'success', 'organization_id' => 'org-123'],
      occurredAt: $occurredAt,
      organizationId: $command->organizationId,
    );

    /** @var AuditEventRepositoryPort&MockObject $repository */
    $repository = $this->createMock(AuditEventRepositoryPort::class);
    $repository->expects(self::once())
      ->method('append')
      ->with(self::callback(
        function (AuditEvent $event) use ($eventId, $occurredAt): bool {
          return $event->id === $eventId
            && 'auth.login_success' === $event->action
            && 'user' === $event->actorType
            && 'user-123' === $event->actorId
            && 'user@example.com' === $event->actorEmail
            && 'email-hash' === $event->actorEmailHash
            && 'token' === $event->subjectType
            && 'token-123' === $event->subjectId
            && 'client-123' === $event->clientId
            && 'tenant-123' === $event->tenantId
            && 'org-123' === $event->organizationId
            && '203.0.113.10' === $event->ipAddress
            && 'ip-hash' === $event->ipHash
            && 'Mozilla' === $event->userAgent
            // organizationId is synced into the hash-covered metadata copy
            // by the handler, so the persisted event carries both.
            && ['reason' => 'success', 'organization_id' => 'org-123'] === $event->metadata
            && $event->occurredAt === $occurredAt;
        },
      ))
      ->willReturn($storedEvent);

    $handler = new RecordAuditEventHandler(
      uuidFactory: $uuidFactory,
      repository: $repository,
    );

    $result = $handler->__invoke($command);

    self::assertInstanceOf(RecordAuditEventResult::class, $result);
    self::assertSame($eventId->value, $result->eventId);
  }

  #[Test]
  public function testInvokeOverwritesAMismatchedMetadataOrganizationIdWithTheCommandField(): void
  {
    $eventId = new Uuid('550e8400-e29b-41d4-a716-446655440001');

    $command = new RecordAuditEventCommand(
      action: 'organization.member_added',
      actorType: 'user',
      organizationId: 'org-truth',
      // Deliberately mismatched: the column value must win, so the
      // hash-covered metadata copy can never diverge from the column
      // that organization-scoped reads filter on.
      metadata: ['organization_id' => 'org-stale'],
    );

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn($eventId);

    /** @var AuditEventRepositoryPort&MockObject $repository */
    $repository = $this->createMock(AuditEventRepositoryPort::class);
    $repository->expects(self::once())
      ->method('append')
      ->with(self::callback(
        static fn (AuditEvent $event): bool => 'org-truth' === $event->organizationId
          && ['organization_id' => 'org-truth'] === $event->metadata,
      ))
      ->willReturnArgument(0);

    $handler = new RecordAuditEventHandler(
      uuidFactory: $uuidFactory,
      repository: $repository,
    );

    $result = $handler->__invoke($command);

    self::assertSame($eventId->value, $result->eventId);
  }
  // #endregion
}
