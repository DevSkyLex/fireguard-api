<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Adapter\Workflow;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Workflow\InterventionWorkflowMutation;
use Intervention\Domain\Event\Workflow\InterventionStatusTransitionedEvent;
use Intervention\Domain\Exception\InterventionConflictException;
use Intervention\Infrastructure\Adapter\Workflow\DoctrineInterventionWorkflowGatewayAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionLabelRecord, InterventionRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_filter;
use function array_map;
use function array_values;

/**
 * Test DoctrineInterventionWorkflowGatewayAdapterTest.
 *
 * Proves the `name` filter of `list('intervention', ...)` is pushed down
 * into SQL through the shared TrigramSearchExpression builder, fixing the
 * latent bug where an unescaped `%`/`_` in the search term was interpreted
 * as a SQL LIKE wildcard instead of a literal character. Also proves the
 * audit-ledger wiring of intervention status transitions: `mutate()`
 * dispatches `InterventionStatusTransitionedEvent` for every successful
 * status change (explicit and work-item-driven auto-start), and dispatches
 * nothing for a non-transition update or a rejected transition.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DoctrineInterventionWorkflowGatewayAdapter::class)]
final class DoctrineInterventionWorkflowGatewayAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655449000';

  private const string LITERAL_MATCH_ID = '660e8400-e29b-41d4-a716-446655449010';

  private const string WILDCARD_DECOY_ID = '660e8400-e29b-41d4-a716-446655449011';

  private const string UNRELATED_ID = '660e8400-e29b-41d4-a716-446655449012';

  private const string TRANSITION_ID = '660e8400-e29b-41d4-a716-446655449013';

  private const string ACTOR_USER_ID = '660e8400-e29b-41d4-a716-446655449901';

  private const string RESPONSIBLE_ID = '660e8400-e29b-41d4-a716-446655449020';

  private const string PARTICIPANT_ID = '660e8400-e29b-41d4-a716-446655449021';

  private const string OTHER_MEMBER_ID = '660e8400-e29b-41d4-a716-446655449022';

  private const string LABEL_ID = '660e8400-e29b-41d4-a716-446655449030';

  private EntityManagerInterface $entityManager;

  private DoctrineInterventionWorkflowGatewayAdapter $adapter;

  private RecordingEventDispatcherPort $eventDispatcher;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->eventDispatcher = new RecordingEventDispatcherPort();
    static::getContainer()->set(EventDispatcherPort::class, $this->eventDispatcher);

    /** @var DoctrineInterventionWorkflowGatewayAdapter $adapter */
    $adapter = static::getContainer()->get(DoctrineInterventionWorkflowGatewayAdapter::class);
    $this->adapter = $adapter;

    $this->createOrganization();
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testListInterventionFiltersByNameTreatingUnderscoreLiterallyNotAsWildcard(): void
  {
    // Contains a literal underscore, which the `name` filter must match.
    $this->createIntervention(self::LITERAL_MATCH_ID, 'a_b Fire Panel Check', 1);
    // Would ALSO match the buggy, unescaped pattern "%a_b%" because SQL LIKE
    // treats "_" as a single-character wildcard -- this is precisely the
    // latent bug TrigramSearchExpression must not reintroduce.
    $this->createIntervention(self::WILDCARD_DECOY_ID, 'axb Fire Panel Check', 2);
    $this->createIntervention(self::UNRELATED_ID, 'Routine Sprinkler Test', 3);

    $this->entityManager->flush();
    $this->entityManager->clear();

    $page = $this->adapter->list('intervention', self::ORGANIZATION_ID, ['name' => 'a_b'], 1, 20);

    self::assertSame(1, $page->total);
    $ids = array_map(static fn ($view) => $view->data['id'], $page->items);
    self::assertSame([self::LITERAL_MATCH_ID], $ids);
  }

  #[Test]
  public function testListInterventionNameFilterIsCaseInsensitiveAndPartial(): void
  {
    $this->createIntervention(self::LITERAL_MATCH_ID, 'Annual Fire Panel Inspection', 1);
    $this->createIntervention(self::UNRELATED_ID, 'Routine Sprinkler Test', 2);

    $this->entityManager->flush();
    $this->entityManager->clear();

    $page = $this->adapter->list('intervention', self::ORGANIZATION_ID, ['name' => 'FIRE PANEL'], 1, 20);

    self::assertSame(1, $page->total);
    self::assertSame(self::LITERAL_MATCH_ID, $page->items[0]->data['id']);
  }

  #[Test]
  public function testMutateInterventionStatusTransitionDispatchesAuditEvent(): void
  {
    $this->createInterventionWithStatus(self::TRANSITION_ID, 'Panel Replacement', 7, 'planned');
    $this->entityManager->flush();

    $this->adapter->mutate(new InterventionWorkflowMutation(
      resource: 'intervention',
      action: 'update',
      userId: self::ACTOR_USER_ID,
      id: self::TRANSITION_ID,
      payload: ['status' => 'in_progress'],
      expectedRevision: 1,
    ));

    $events = $this->eventDispatcher->transitionEvents();
    self::assertCount(1, $events);
    $event = $events[0];
    self::assertSame(self::ORGANIZATION_ID, $event->organizationId);
    self::assertSame(self::TRANSITION_ID, $event->interventionId);
    self::assertSame(7, $event->interventionNumber);
    self::assertSame(self::ACTOR_USER_ID, $event->actorUserId);
    self::assertSame('planned', $event->fromStatus);
    self::assertSame('in_progress', $event->toStatus);
    self::assertNull($event->reviewNote);
  }

  #[Test]
  public function testMutateInterventionChangesRequestedTransitionCarriesReviewNote(): void
  {
    $this->createInterventionWithStatus(self::TRANSITION_ID, 'Panel Replacement', 8, 'submitted');
    $this->entityManager->flush();

    $this->adapter->mutate(new InterventionWorkflowMutation(
      resource: 'intervention',
      action: 'update',
      userId: self::ACTOR_USER_ID,
      id: self::TRANSITION_ID,
      payload: ['status' => 'changes_requested', 'reviewNote' => 'Please redo the panel check.'],
      expectedRevision: 1,
    ));

    $events = $this->eventDispatcher->transitionEvents();
    self::assertCount(1, $events);
    self::assertSame('submitted', $events[0]->fromStatus);
    self::assertSame('changes_requested', $events[0]->toStatus);
    self::assertSame('Please redo the panel check.', $events[0]->reviewNote);
  }

  #[Test]
  public function testMutateInterventionFieldOnlyEditDoesNotDispatchAuditEvent(): void
  {
    $this->createInterventionWithStatus(self::TRANSITION_ID, 'Panel Replacement', 9, 'planned');
    $this->entityManager->flush();

    $this->adapter->mutate(new InterventionWorkflowMutation(
      resource: 'intervention',
      action: 'update',
      userId: self::ACTOR_USER_ID,
      id: self::TRANSITION_ID,
      payload: ['description' => 'Updated scope, no status change.'],
      expectedRevision: 1,
    ));

    self::assertSame([], $this->eventDispatcher->transitionEvents());
  }

  #[Test]
  public function testMutateInterventionRejectedTransitionDoesNotDispatchAuditEvent(): void
  {
    // `draft -> changes_requested` is not a legal edge (InterventionTransitionPolicy);
    // the aggregate must reject it before any activity/event is recorded.
    $this->createInterventionWithStatus(self::TRANSITION_ID, 'Panel Replacement', 10, 'draft');
    $this->entityManager->flush();

    $this->expectException(InterventionConflictException::class);

    try {
      $this->adapter->mutate(new InterventionWorkflowMutation(
        resource: 'intervention',
        action: 'update',
        userId: self::ACTOR_USER_ID,
        id: self::TRANSITION_ID,
        payload: ['status' => 'changes_requested'],
        expectedRevision: 1,
      ));
    } finally {
      self::assertSame([], $this->eventDispatcher->transitionEvents());
    }
  }

  #[Test]
  public function testListInterventionFiltersByNumber(): void
  {
    $this->createIntervention(self::LITERAL_MATCH_ID, 'Numbered Intervention', 42);
    $this->createIntervention(self::UNRELATED_ID, 'Other Intervention', 43);

    $this->entityManager->flush();
    $this->entityManager->clear();

    $page = $this->adapter->list('intervention', self::ORGANIZATION_ID, ['number' => 42], 1, 20);

    self::assertSame(1, $page->total);
    self::assertSame(self::LITERAL_MATCH_ID, $page->items[0]->data['id']);
  }

  #[Test]
  public function testListInterventionFiltersByLabelId(): void
  {
    $this->createLabel();
    $this->createIntervention(self::LITERAL_MATCH_ID, 'Labeled Intervention', 1);
    $this->createIntervention(self::UNRELATED_ID, 'Unlabeled Intervention', 2);

    /** @var InterventionRecord $labeled */
    $labeled = $this->entityManager->find(InterventionRecord::class, self::LITERAL_MATCH_ID);
    /** @var InterventionLabelRecord $label */
    $label = $this->entityManager->find(InterventionLabelRecord::class, self::LABEL_ID);
    $labeled->labels->add($label);

    $this->entityManager->flush();
    $this->entityManager->clear();

    $page = $this->adapter->list('intervention', self::ORGANIZATION_ID, ['labelId' => self::LABEL_ID], 1, 20);

    self::assertSame(1, $page->total);
    self::assertSame(self::LITERAL_MATCH_ID, $page->items[0]->data['id']);
  }

  #[Test]
  public function testListInterventionFiltersByMemberMatchingResponsibleOrParticipant(): void
  {
    $this->createIntervention(self::LITERAL_MATCH_ID, 'Responsible Intervention', 1, responsibleId: self::RESPONSIBLE_ID);
    $this->createIntervention(self::WILDCARD_DECOY_ID, 'Participant Intervention', 2, participants: [self::PARTICIPANT_ID]);
    $this->createIntervention(self::UNRELATED_ID, 'Unrelated Intervention', 3, responsibleId: self::OTHER_MEMBER_ID);

    $this->entityManager->flush();
    $this->entityManager->clear();

    $responsibleMatches = $this->adapter->list('intervention', self::ORGANIZATION_ID, ['memberId' => self::RESPONSIBLE_ID], 1, 20);
    self::assertSame(1, $responsibleMatches->total);
    self::assertSame(self::LITERAL_MATCH_ID, $responsibleMatches->items[0]->data['id']);

    $participantMatches = $this->adapter->list('intervention', self::ORGANIZATION_ID, ['memberId' => self::PARTICIPANT_ID], 1, 20);
    self::assertSame(1, $participantMatches->total);
    self::assertSame(self::WILDCARD_DECOY_ID, $participantMatches->items[0]->data['id']);

    $noMatches = $this->adapter->list('intervention', self::ORGANIZATION_ID, ['memberId' => '660e8400-e29b-41d4-a716-446655449099'], 1, 20);
    self::assertSame(0, $noMatches->total);
  }

  private function createOrganization(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Intervention Workflow Search Test';
    $organization->slug = 'intervention-workflow-search-test';
    $organization->ownerUserId = '660e8400-e29b-41d4-a716-446655449900';
    $organization->createdByUserId = '660e8400-e29b-41d4-a716-446655449900';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  /**
   * @param list<string> $participants
   */
  private function createIntervention(
    string $id,
    string $name,
    int $number,
    ?string $responsibleId = null,
    array $participants = [],
  ): void {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $record = new InterventionRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->type = 'site_setup';
    $record->name = $name;
    $record->number = $number;
    $record->status = 'draft';
    $record->priority = 'normal';
    $record->responsibleId = $responsibleId;
    $record->participants = $participants;
    $record->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $record->updatedAt = $record->createdAt;
    $this->entityManager->persist($record);
  }

  private function createInterventionWithStatus(string $id, string $name, int $number, string $status): void
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $now = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $record = new InterventionRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->type = 'site_setup';
    $record->name = $name;
    $record->number = $number;
    $record->status = $status;
    $record->priority = 'normal';
    $record->siteId = null;
    $record->responsibleId = self::ACTOR_USER_ID;
    $record->plannedStartAt = $now;
    $record->dueAt = $now->modify('+7 days');
    $record->revision = 1;
    $record->createdAt = $now;
    $record->updatedAt = $now;
    $this->entityManager->persist($record);
  }

  private function createLabel(): void
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $label = new InterventionLabelRecord();
    $label->id = self::LABEL_ID;
    $label->organization = $organization;
    $label->name = 'Recall';
    $label->color = '#ff0000';
    $label->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $label->updatedAt = $label->createdAt;
    $this->entityManager->persist($label);
  }

  private function cleanup(): void
  {
    // Raw SQL, not ORM remove()/flush(): the ORM's cascade-persist validation
    // is unreliable across the PESSIMISTIC_WRITE-locked, wrapInTransaction-scoped
    // writes the mutate() tests exercise below, mirroring
    // DoctrineInterventionActivityAdapterTest::cleanup().
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM intervention_activities WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM intervention_label_assignments WHERE label_id IN (SELECT id FROM intervention_labels WHERE organization_id = :organizationId)',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM interventions WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM intervention_labels WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}

/**
 * Test double RecordingEventDispatcherPort.
 *
 * Records every dispatched domain event in memory so the integration test
 * can assert on `InterventionStatusTransitionedEvent` payloads without a
 * real Symfony event dispatcher / Audit subscriber round-trip.
 *
 * @category Test Double
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RecordingEventDispatcherPort implements EventDispatcherPort
{
  /**
   * @var list<object>
   */
  private array $events = [];

  public function dispatch(object $event): void
  {
    $this->events[] = $event;
  }

  public function dispatchAll(array $events): void
  {
    foreach ($events as $event) {
      $this->dispatch($event);
    }
  }

  /**
   * @return list<InterventionStatusTransitionedEvent>
   */
  public function transitionEvents(): array
  {
    return array_values(array_filter(
      $this->events,
      static fn (object $event): bool => $event instanceof InterventionStatusTransitionedEvent,
    ));
  }
}
