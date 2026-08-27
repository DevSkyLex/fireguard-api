<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Infrastructure\EventSubscriber;

use Audit\Application\UseCase\Command\RecordAuditEvent\{RecordAuditEventCommand, RecordAuditEventResult};
use Audit\Infrastructure\EventSubscriber\AuditEventSubscriber;
use Audit\Infrastructure\Service\AuditPiiSanitizer;
use Automation\Domain\Event\Rule\{AutomationRuleExecutedEvent, AutomationRuleFailedEvent};
use Calendar\Domain\Event\{CalendarEventCreatedEvent, CalendarEventDeletedEvent, CalendarEventUpdatedEvent};
use DateTimeImmutable;
use Intervention\Domain\Event\Export\InterventionsExportedEvent;
use Intervention\Domain\Event\Recurrence\{
  InterventionRecurrenceCreatedEvent,
  InterventionRecurrenceDeletedEvent,
  InterventionRecurrenceMaterializedEvent,
  InterventionRecurrenceUpdatedEvent
};
use Maintenance\Domain\Event\Campaign\MaintenanceCampaignGeneratedEvent;
use Maintenance\Domain\Event\Schedule\MaintenanceScheduleOverriddenEvent;
use Organization\Domain\Event\Team\{TeamDeletedEvent, TeamMemberRemovedEvent, TeamUpdatedEvent};
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
 * Test OperationsAuditWiringTest.
 *
 * Wiring proof for the team / recurrence / maintenance / automation
 * slice: every domain event, dispatched through the real event-name
 * derivation of SymfonyEventDispatcherAdapter, reaches
 * AuditEventSubscriber and produces the expected audit action,
 * subject and metadata.
 *
 * @category Event Subscriber Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: AuditEventSubscriber::class)]
final class OperationsAuditWiringTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = 'org-1';

  private const string ACTOR_USER_ID = 'user-1';
  // #endregion

  // #region Tests
  #[Test]
  public function testTeamLifecycleEventsProduceTheirAuditRecords(): void
  {
    $events = [
      'organization.team_updated' => new TeamUpdatedEvent(organizationId: self::ORGANIZATION_ID, teamId: 'team-1', name: 'Night shift'),
      'organization.team_deleted' => new TeamDeletedEvent(organizationId: self::ORGANIZATION_ID, teamId: 'team-1'),
      'organization.team_member_removed' => new TeamMemberRemovedEvent(organizationId: self::ORGANIZATION_ID, teamId: 'team-1', memberId: 'member-1'),
    ];

    $expected = [
      'organization.team_updated' => ['organization_team', 'team-1', ['name' => 'Night shift', 'organization_id' => self::ORGANIZATION_ID]],
      'organization.team_deleted' => ['organization_team', 'team-1', ['organization_id' => self::ORGANIZATION_ID]],
      'organization.team_member_removed' => ['organization_team_member', 'member-1', ['team_id' => 'team-1', 'organization_id' => self::ORGANIZATION_ID]],
    ];

    $this->assertEventsProduce($events, $expected);
  }

  #[Test]
  public function testInterventionRecurrenceEventsProduceTheirAuditRecords(): void
  {
    $events = [
      'intervention.recurrence_created' => new InterventionRecurrenceCreatedEvent(organizationId: self::ORGANIZATION_ID, recurrenceId: 'rec-1', templateId: 'tpl-1', actorUserId: self::ACTOR_USER_ID),
      'intervention.recurrence_updated' => new InterventionRecurrenceUpdatedEvent(organizationId: self::ORGANIZATION_ID, recurrenceId: 'rec-1', actorUserId: self::ACTOR_USER_ID),
      'intervention.recurrence_deleted' => new InterventionRecurrenceDeletedEvent(organizationId: self::ORGANIZATION_ID, recurrenceId: 'rec-1', actorUserId: self::ACTOR_USER_ID),
      'intervention.recurrence_materialized' => new InterventionRecurrenceMaterializedEvent(organizationId: self::ORGANIZATION_ID, recurrenceId: 'rec-1', succeeded: false, interventionId: null, error: 'template removed'),
    ];

    $expected = [
      'intervention.recurrence_created' => ['intervention_recurrence', 'rec-1', ['template_id' => 'tpl-1', 'organization_id' => self::ORGANIZATION_ID]],
      'intervention.recurrence_updated' => ['intervention_recurrence', 'rec-1', ['organization_id' => self::ORGANIZATION_ID]],
      'intervention.recurrence_deleted' => ['intervention_recurrence', 'rec-1', ['organization_id' => self::ORGANIZATION_ID]],
      'intervention.recurrence_materialized' => ['intervention_recurrence', 'rec-1', ['succeeded' => false, 'intervention_id' => null, 'error' => 'template removed', 'organization_id' => self::ORGANIZATION_ID]],
    ];

    $this->assertEventsProduce($events, $expected);
  }

  #[Test]
  public function testInterventionsExportedEventProducesItsAuditRecord(): void
  {
    $events = [
      'intervention.list_exported' => new InterventionsExportedEvent(
        organizationId: self::ORGANIZATION_ID,
        actorUserId: self::ACTOR_USER_ID,
        format: 'csv',
        rowCount: 42,
        filterKeys: ['status', 'type'],
      ),
    ];

    $expected = [
      'intervention.list_exported' => ['organization', self::ORGANIZATION_ID, ['row_count' => 42, 'filter_keys' => ['status', 'type'], 'organization_id' => self::ORGANIZATION_ID]],
    ];

    $this->assertEventsProduce($events, $expected);
  }

  #[Test]
  public function testCalendarEventEventsProduceTheirAuditRecords(): void
  {
    $startsAt = new DateTimeImmutable('2026-08-01T09:00:00+02:00');

    $events = [
      'calendar.event_created' => new CalendarEventCreatedEvent(organizationId: self::ORGANIZATION_ID, eventId: 'event-1', title: 'Fire drill', startsAt: $startsAt, actorUserId: self::ACTOR_USER_ID),
      'calendar.event_updated' => new CalendarEventUpdatedEvent(organizationId: self::ORGANIZATION_ID, eventId: 'event-1', title: 'Updated drill', startsAt: $startsAt, actorUserId: self::ACTOR_USER_ID),
      'calendar.event_deleted' => new CalendarEventDeletedEvent(organizationId: self::ORGANIZATION_ID, eventId: 'event-1', actorUserId: self::ACTOR_USER_ID),
    ];

    $expected = [
      'calendar.event_created' => ['calendar_event', 'event-1', ['title' => 'Fire drill', 'starts_at' => $startsAt->format(DateTimeImmutable::ATOM), 'organization_id' => self::ORGANIZATION_ID]],
      'calendar.event_updated' => ['calendar_event', 'event-1', ['title' => 'Updated drill', 'starts_at' => $startsAt->format(DateTimeImmutable::ATOM), 'organization_id' => self::ORGANIZATION_ID]],
      'calendar.event_deleted' => ['calendar_event', 'event-1', ['organization_id' => self::ORGANIZATION_ID]],
    ];

    $this->assertEventsProduce($events, $expected);
  }

  #[Test]
  public function testMaintenanceAndAutomationEventsProduceTheirAuditRecords(): void
  {
    $events = [
      'maintenance.schedule_overridden' => new MaintenanceScheduleOverriddenEvent(organizationId: self::ORGANIZATION_ID, scheduleId: 'sched-1', equipmentId: 'equip-1', intervalOverride: 'P3M', actorUserId: self::ACTOR_USER_ID),
      'maintenance.campaign_generated' => new MaintenanceCampaignGeneratedEvent(organizationId: self::ORGANIZATION_ID, interventionId: 'int-1', workItemsCount: 12, actorUserId: self::ACTOR_USER_ID),
      'automation.rule_executed' => new AutomationRuleExecutedEvent(ruleKey: 'nc.critical_opens_intervention', organizationId: self::ORGANIZATION_ID, subjectId: 'nc-1', interventionId: 'int-1'),
      'automation.rule_failed' => new AutomationRuleFailedEvent(ruleKey: 'nc.critical_opens_intervention', organizationId: self::ORGANIZATION_ID, subjectId: 'nc-1', error: 'no default template'),
    ];

    $expected = [
      'maintenance.schedule_overridden' => ['maintenance_schedule', 'sched-1', ['equipment_id' => 'equip-1', 'interval_override' => 'P3M', 'organization_id' => self::ORGANIZATION_ID]],
      'maintenance.campaign_generated' => ['intervention', 'int-1', ['work_items_count' => 12, 'organization_id' => self::ORGANIZATION_ID]],
      'automation.rule_executed' => ['automation_rule', 'nc-1', ['rule_key' => 'nc.critical_opens_intervention', 'intervention_id' => 'int-1', 'organization_id' => self::ORGANIZATION_ID]],
      'automation.rule_failed' => ['automation_rule', 'nc-1', ['rule_key' => 'nc.critical_opens_intervention', 'error' => 'no default template', 'organization_id' => self::ORGANIZATION_ID]],
    ];

    $this->assertEventsProduce($events, $expected);
  }

  /**
   * @param array<string, object> $events
   * @param array<string, array{string, string, array<string, mixed>}> $expected
   */
  private function assertEventsProduce(array $events, array $expected): void
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
      sanitizer: new AuditPiiSanitizer(includePii: true, piiSalt: 'salt-for-tests'),
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

    self::assertCount(count($expected), $recorded);

    $actions = [];
    foreach ($recorded as $command) {
      $actions[] = $command->action;
      [$subjectType, $subjectId, $metadata] = $expected[$command->action];
      self::assertSame($subjectType, $command->subjectType, sprintf('subjectType mismatch for %s', $command->action));
      self::assertSame($subjectId, $command->subjectId, sprintf('subjectId mismatch for %s', $command->action));
      self::assertSame($metadata, $command->metadata, sprintf('metadata mismatch for %s', $command->action));
    }

    self::assertSame(array_keys($expected), $actions);
  }
  // #endregion
}
