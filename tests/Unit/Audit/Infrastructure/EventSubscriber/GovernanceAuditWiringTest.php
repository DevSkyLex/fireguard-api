<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Infrastructure\EventSubscriber;

use Approval\Domain\Event\Request\{
  ApprovalApprovedEvent,
  ApprovalExecutionFailedEvent,
  ApprovalExpiredEvent,
  ApprovalRejectedEvent,
  ApprovalRequestedEvent
};
use Audit\Application\UseCase\Command\RecordAuditEvent\{RecordAuditEventCommand, RecordAuditEventResult};
use Audit\Infrastructure\EventSubscriber\AuditEventSubscriber;
use Audit\Infrastructure\Service\AuditPiiSanitizer;
use Compliance\Domain\Event\SafetyRegisterExportedEvent;
use Import\Domain\Event\{ImportJobCompletedEvent, ImportJobFailedEvent};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Infrastructure\EventDispatcher\SymfonyEventDispatcherAdapter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;

use function array_keys;
use function count;
use function sprintf;

/**
 * Test GovernanceAuditWiringTest.
 *
 * Wiring proof for the approval / import / compliance slice: every
 * domain event, dispatched through the real event-name derivation of
 * SymfonyEventDispatcherAdapter, reaches AuditEventSubscriber and
 * produces the expected audit action, subject and metadata.
 *
 * @category Event Subscriber Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: AuditEventSubscriber::class)]
final class GovernanceAuditWiringTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = 'org-1';

  private const string REQUEST_ID = 'approval-1';

  private const string ACTOR_USER_ID = 'user-1';
  // #endregion

  // #region Tests
  #[Test]
  public function testEveryApprovalDomainEventProducesItsAuditRecord(): void
  {
    $events = [
      new ApprovalRequestedEvent(
        organizationId: self::ORGANIZATION_ID,
        requestId: self::REQUEST_ID,
        actionType: 'equipment.decommission',
        subjectId: 'equip-1',
        requestedByMemberId: 'member-1',
        requestedByUserId: self::ACTOR_USER_ID,
      ),
      new ApprovalApprovedEvent(
        organizationId: self::ORGANIZATION_ID,
        requestId: self::REQUEST_ID,
        actionType: 'equipment.decommission',
        subjectId: 'equip-1',
        decisionByMemberId: 'member-2',
        decisionByUserId: self::ACTOR_USER_ID,
      ),
      new ApprovalRejectedEvent(
        organizationId: self::ORGANIZATION_ID,
        requestId: self::REQUEST_ID,
        actionType: 'equipment.decommission',
        subjectId: 'equip-1',
        decisionByMemberId: 'member-2',
        decisionByUserId: self::ACTOR_USER_ID,
      ),
      new ApprovalExpiredEvent(
        organizationId: self::ORGANIZATION_ID,
        requestId: self::REQUEST_ID,
        actionType: 'equipment.decommission',
        subjectId: 'equip-1',
      ),
      new ApprovalExecutionFailedEvent(
        organizationId: self::ORGANIZATION_ID,
        requestId: self::REQUEST_ID,
        actionType: 'equipment.decommission',
        subjectId: 'equip-1',
        error: 'equipment already decommissioned',
        decisionByUserId: self::ACTOR_USER_ID,
      ),
    ];

    $expected = [
      'approval.requested' => ['approval_request', self::REQUEST_ID, ['action_type' => 'equipment.decommission', 'subject_id' => 'equip-1', 'requested_by_member_id' => 'member-1', 'organization_id' => self::ORGANIZATION_ID], 'user'],
      'approval.approved' => ['approval_request', self::REQUEST_ID, ['action_type' => 'equipment.decommission', 'subject_id' => 'equip-1', 'decision_by_member_id' => 'member-2', 'organization_id' => self::ORGANIZATION_ID], 'user'],
      'approval.rejected' => ['approval_request', self::REQUEST_ID, ['action_type' => 'equipment.decommission', 'subject_id' => 'equip-1', 'decision_by_member_id' => 'member-2', 'organization_id' => self::ORGANIZATION_ID], 'user'],
      'approval.expired' => ['approval_request', self::REQUEST_ID, ['action_type' => 'equipment.decommission', 'subject_id' => 'equip-1', 'organization_id' => self::ORGANIZATION_ID], 'system'],
      'approval.execution_failed' => ['approval_request', self::REQUEST_ID, ['action_type' => 'equipment.decommission', 'subject_id' => 'equip-1', 'error' => 'equipment already decommissioned', 'organization_id' => self::ORGANIZATION_ID], 'user'],
    ];

    $this->assertEventsProduce($events, $expected);
  }

  #[Test]
  public function testEveryImportAndComplianceDomainEventProducesItsAuditRecord(): void
  {
    $events = [
      new ImportJobCompletedEvent(
        importJobId: 'import-1',
        organizationId: self::ORGANIZATION_ID,
        kind: 'equipment',
        totalRows: 10,
        successfulRows: 8,
        failedRows: 2,
        createdBy: self::ACTOR_USER_ID,
      ),
      new ImportJobFailedEvent(
        importJobId: 'import-1',
        organizationId: self::ORGANIZATION_ID,
        kind: 'equipment',
        jobError: 'malformed header',
        createdBy: self::ACTOR_USER_ID,
      ),
      new SafetyRegisterExportedEvent(
        organizationId: self::ORGANIZATION_ID,
        facilityId: null,
        actorUserId: self::ACTOR_USER_ID,
        planKey: 'pro',
        scope: 'organization',
        generatedAt: '2026-01-01T00:00:00+00:00',
      ),
      new SafetyRegisterExportedEvent(
        organizationId: self::ORGANIZATION_ID,
        facilityId: 'fac-1',
        actorUserId: self::ACTOR_USER_ID,
        planKey: 'pro',
        scope: 'facility',
        generatedAt: '2026-01-01T00:00:00+00:00',
      ),
    ];

    /** @var list<RecordAuditEventCommand> $recorded */
    $recorded = $this->dispatchAll($events);

    self::assertCount(4, $recorded);

    self::assertSame('import.job_completed', $recorded[0]->action);
    self::assertSame('import_job', $recorded[0]->subjectType);
    self::assertSame('import-1', $recorded[0]->subjectId);
    self::assertSame(
      ['kind' => 'equipment', 'total_rows' => 10, 'successful_rows' => 8, 'failed_rows' => 2, 'organization_id' => self::ORGANIZATION_ID],
      $recorded[0]->metadata,
    );

    self::assertSame('import.job_failed', $recorded[1]->action);
    self::assertSame(
      ['kind' => 'equipment', 'job_error' => 'malformed header', 'organization_id' => self::ORGANIZATION_ID],
      $recorded[1]->metadata,
    );

    self::assertSame('compliance.register_exported', $recorded[2]->action);
    self::assertSame('organization', $recorded[2]->subjectType);
    self::assertSame(self::ORGANIZATION_ID, $recorded[2]->subjectId);

    self::assertSame('facility', $recorded[3]->subjectType);
    self::assertSame('fac-1', $recorded[3]->subjectId);
  }

  /**
   * @param list<object> $events
   * @param array<string, array{string, string, array<string, mixed>, string}> $expected
   */
  private function assertEventsProduce(array $events, array $expected): void
  {
    $recorded = $this->dispatchAll($events);

    self::assertCount(count($expected), $recorded);

    $actions = [];
    foreach ($recorded as $command) {
      $actions[] = $command->action;
      [$subjectType, $subjectId, $metadata, $actorType] = $expected[$command->action];
      self::assertSame($subjectType, $command->subjectType, sprintf('subjectType mismatch for %s', $command->action));
      self::assertSame($subjectId, $command->subjectId, sprintf('subjectId mismatch for %s', $command->action));
      self::assertSame($metadata, $command->metadata, sprintf('metadata mismatch for %s', $command->action));
      self::assertSame($actorType, $command->actorType, sprintf('actorType mismatch for %s', $command->action));
    }

    self::assertSame(array_keys($expected), $actions);
  }

  /**
   * @param list<object> $events
   *
   * @return list<RecordAuditEventCommand>
   */
  private function dispatchAll(array $events): array
  {
    /** @var list<RecordAuditEventCommand> $recorded */
    $recorded = [];
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willReturnCallback(static function (RecordAuditEventCommand $command) use (&$recorded): RecordAuditEventResult {
        $recorded[] = $command;

        return new RecordAuditEventResult(eventId: 'event-1');
      });

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $subscriber = new AuditEventSubscriber(
      commandBus: $commandBus,
      sanitizer: new AuditPiiSanitizer(includePii: true, piiSalt: null),
      requestStack: new RequestStack(),
      security: $security,
      logger: new NullLogger(),
    );

    $symfonyDispatcher = new EventDispatcher();
    $symfonyDispatcher->addSubscriber($subscriber);
    $adapter = new SymfonyEventDispatcherAdapter(
      eventDispatcher: $symfonyDispatcher,
      logger: new NullLogger(),
    );

    foreach ($events as $event) {
      $adapter->dispatch($event);
    }

    return $recorded;
  }
  // #endregion
}
