<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Resource\{
  InterventionListMetrics,
  InterventionResourceSummary,
  InterventionValidationContext,
  InterventionWorkItemSummary
};
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\InterventionIssueFinder;
use Intervention\Infrastructure\Persistence\Doctrine\Mapper\InterventionViewMapper;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{
  InterventionActivityRecord,
  InterventionAttachmentRecord,
  InterventionChangeRecord,
  InterventionLabelRecord,
  InterventionRecord,
  InterventionRecurrenceRecord,
  InterventionRecurrenceRunRecord,
  InterventionTemplateRecord,
  InterventionWorkItemRecord
};
use Intervention\Infrastructure\Persistence\Doctrine\Repository\InterventionAttachmentRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test InterventionViewMapper.
 *
 * Integration coverage for the read-shaping mapper against the real `main`
 * database. The two non-DB collaborators (the resource gateway port and the
 * issue finder) are stubbed so the derived metrics of the metrics-less branch
 * are deterministic; every other value (comment counts, proposed-change counts,
 * labels, organization resolution) is exercised through the real entity
 * manager. The mapper is constructed by hand — it cannot be fetched from the
 * container and still receive stubbed collaborators.
 *
 * @category Mapper Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionViewMapper::class)]
final class InterventionViewMapperTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'a10e8400-e29b-41d4-a716-446655440001';

  private const string INTERVENTION_ID = 'a10e8400-e29b-41d4-a716-446655440002';

  private const string INVENTORY_INTERVENTION_ID = 'a10e8400-e29b-41d4-a716-446655440003';

  private const string MINIMAL_INTERVENTION_ID = 'a10e8400-e29b-41d4-a716-446655440004';

  private const string WORK_ITEM_ID = 'a10e8400-e29b-41d4-a716-446655440005';

  private const string LABEL_ID = 'a10e8400-e29b-41d4-a716-446655440006';

  private const string SITE_ID = 'a10e8400-e29b-41d4-a716-446655440101';

  private const string RESPONSIBLE_ID = 'a10e8400-e29b-41d4-a716-446655440102';

  private const string PARTICIPANT_ID = 'a10e8400-e29b-41d4-a716-446655440103';

  private const string ACTOR_ID = 'a10e8400-e29b-41d4-a716-446655440104';

  private const string ASSIGNEE_ID = 'a10e8400-e29b-41d4-a716-446655440105';

  private const string ACTIVITY_ID = 'a10e8400-e29b-41d4-a716-446655440201';

  private const string CHANGE_WITH_WORK_ITEM_ID = 'a10e8400-e29b-41d4-a716-446655440301';

  private const string CHANGE_WITHOUT_WORK_ITEM_ID = 'a10e8400-e29b-41d4-a716-446655440302';

  /**
   * An intervention the recurrence sweep materialized: a successful
   * `intervention_recurrence_runs` row points at it.
   */
  private const string MATERIALIZED_INTERVENTION_ID = 'a10e8400-e29b-41d4-a716-446655440007';

  private const string TEMPLATE_ID = 'a10e8400-e29b-41d4-a716-446655440401';

  private const string RECURRENCE_ID = 'a10e8400-e29b-41d4-a716-446655440402';

  private const string RECURRENCE_RUN_ID = 'a10e8400-e29b-41d4-a716-446655440403';

  private EntityManagerInterface $entityManager;

  private InterventionViewMapper $mapper;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    // The metrics-less branch reads three resource summaries and the issue
    // list. Stubbing the port keeps them deterministic (reads only) while the
    // real issue finder still runs its branching logic over those values.
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('summary')->willReturn(new InterventionResourceSummary(0, 2, 1));
    $resources->method('workItemSummary')->willReturn(new InterventionWorkItemSummary(3, 0, 0, 0, 2));
    $resources->method('validationContext')->willReturn(new InterventionValidationContext('site_setup', 'draft', null, null));
    $resources->method('equipmentDrafts')->willReturn([]);

    $this->mapper = new InterventionViewMapper(
      $this->entityManager,
      $resources,
      new InterventionIssueFinder($resources, new InterventionAttachmentRepository($this->entityManager)),
    );

    $this->seed();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testInterventionViewWithPreloadedMetricsAppliesSiteSetupAndRequiredBlockers(): void
  {
    $intervention = $this->entityManager->find(InterventionRecord::class, self::INTERVENTION_ID);
    self::assertInstanceOf(InterventionRecord::class, $intervention);

    $metrics = new InterventionListMetrics(
      facilities: 0,
      equipment: 5,
      inspections: 2,
      workItems: 0,
      completedWorkItems: 4,
      requiredIncomplete: 2,
      proposedChanges: 3,
      resourceBlockers: 1,
    );

    $view = $this->mapper->interventionView($intervention, $metrics);

    self::assertSame('intervention', $view->resource);
    self::assertSame(self::ORGANIZATION_ID, $view->organizationId);

    $data = $view->data;
    self::assertSame(self::INTERVENTION_ID, $data['id']);
    self::assertSame('/api/organizations/' . self::ORGANIZATION_ID, $data['organization']);
    self::assertSame(1, $data['number']);
    self::assertSame('site_setup', $data['type']);
    self::assertSame('Annual Fire Panel Inspection', $data['name']);
    self::assertSame('Full annual inspection.', $data['description']);
    self::assertSame('review', $data['status']);
    self::assertSame('/api/facilities/' . self::SITE_ID, $data['site']);
    self::assertSame('/api/organizations/' . self::ORGANIZATION_ID . '/members/' . self::RESPONSIBLE_ID, $data['responsible']);
    self::assertSame([
      '/api/organizations/' . self::ORGANIZATION_ID . '/members/' . self::RESPONSIBLE_ID,
      '/api/organizations/' . self::ORGANIZATION_ID . '/members/' . self::PARTICIPANT_ID,
    ], $data['participants']);
    self::assertSame('high', $data['priority']);
    self::assertNotNull($data['plannedStartAt']);
    self::assertSame($intervention->plannedStartAt?->format('c'), $data['plannedStartAt']);
    self::assertSame($intervention->dueAt?->format('c'), $data['dueAt']);
    self::assertSame('Awaiting sign-off.', $data['reviewNote']);
    self::assertSame(3, $data['revision']);

    // Preloaded-metrics branch: counts come straight from the metrics DTO.
    self::assertSame(0, $data['facilitiesCount']);
    self::assertSame(5, $data['equipmentCount']);
    self::assertSame(2, $data['inspectionsCount']);
    // resourceBlockers(1) + site_setup&facilities==0(+1) + requiredIncomplete>0(+1) = 3.
    self::assertSame(3, $data['blockersCount']);
    self::assertSame(0, $data['workItemsCount']);
    self::assertSame(4, $data['completedWorkItemsCount']);
    self::assertSame(3, $data['proposedChangesCount']);

    // Comment count and labels are read live from the real database.
    self::assertSame(2, $data['commentsCount']);
    // A signature attachment was seeded for this intervention.
    self::assertTrue($data['hasSignature']);
    self::assertSame([['id' => self::LABEL_ID, 'name' => 'Priority', 'color' => '#ff8800']], $data['labels']);
    self::assertSame($intervention->createdAt->format('c'), $data['createdAt']);
    self::assertSame($intervention->updatedAt->format('c'), $data['updatedAt']);
  }

  #[Test]
  public function testInterventionViewWithPreloadedMetricsAddsInventoryWorkItemBlocker(): void
  {
    $inventory = $this->entityManager->find(InterventionRecord::class, self::INVENTORY_INTERVENTION_ID);
    self::assertInstanceOf(InterventionRecord::class, $inventory);

    $metrics = new InterventionListMetrics(
      facilities: 3,
      workItems: 0,
      resourceBlockers: 0,
    );

    $view = $this->mapper->interventionView($inventory, $metrics);

    $data = $view->data;
    self::assertSame('inventory', $data['type']);
    // resourceBlockers(0) + inventory&workItems==0(+1) = 1; the site_setup and
    // requiredIncomplete clauses do not apply here.
    self::assertSame(1, $data['blockersCount']);
    // Empty-graph paths: no comments, no proposed changes, no labels.
    self::assertSame(0, $data['commentsCount']);
    self::assertSame(0, $data['proposedChangesCount']);
    self::assertSame([], $data['labels']);
    self::assertNull($data['site']);
    self::assertSame([], $data['participants']);
  }

  #[Test]
  public function testInterventionViewWithoutMetricsResolvesResourcesAndCountsFromDatabase(): void
  {
    $minimal = $this->entityManager->find(InterventionRecord::class, self::MINIMAL_INTERVENTION_ID);
    self::assertInstanceOf(InterventionRecord::class, $minimal);

    $view = $this->mapper->interventionView($minimal);

    $data = $view->data;
    // Null-relation branches of every optional field.
    self::assertNull($data['site']);
    self::assertNull($data['responsible']);
    self::assertSame([], $data['participants']);
    self::assertNull($data['plannedStartAt']);
    self::assertNull($data['dueAt']);
    self::assertNull($data['reviewNote']);
    self::assertNull($data['description']);

    // Resource summary is taken from the stubbed gateway.
    self::assertSame(0, $data['facilitiesCount']);
    self::assertSame(2, $data['equipmentCount']);
    self::assertSame(1, $data['inspectionsCount']);
    // Issue finder over the stubbed summary: site_setup with zero facilities
    // yields exactly one blocker.
    self::assertSame(1, $data['blockersCount']);
    self::assertSame(3, $data['workItemsCount']);
    self::assertSame(2, $data['completedWorkItemsCount']);

    // Proposed-change and comment counts are queried from the real database:
    // one proposed change (an applied one is filtered out) and one comment
    // (a system activity is filtered out).
    self::assertSame(1, $data['proposedChangesCount']);
    self::assertSame(1, $data['commentsCount']);
    self::assertFalse($data['hasSignature']);
    self::assertSame([], $data['labels']);
    // Manually created: no run row links it to a recurrence.
    self::assertNull($data['recurrence']);
  }

  #[Test]
  public function testInterventionViewWithoutMetricsResolvesTheRecurrenceThatMaterializedIt(): void
  {
    $materialized = $this->entityManager->find(InterventionRecord::class, self::MATERIALIZED_INTERVENTION_ID);
    self::assertInstanceOf(InterventionRecord::class, $materialized);

    $view = $this->mapper->interventionView($materialized);

    // `intervention_recurrence_runs` is the only link back to the series;
    // before this, the row existed and nothing could read it.
    self::assertSame(
      expected: '/api/intervention-recurrences/' . self::RECURRENCE_ID,
      actual: $view->data['recurrence'],
      message: 'A materialized intervention must expose the recurrence that produced it.',
    );
  }

  #[Test]
  public function testInterventionViewWithPreloadedMetricsLeavesTheRecurrenceUnresolved(): void
  {
    $materialized = $this->entityManager->find(InterventionRecord::class, self::MATERIALIZED_INTERVENTION_ID);
    self::assertInstanceOf(InterventionRecord::class, $materialized);

    $view = $this->mapper->interventionView($materialized, new InterventionListMetrics());

    // Deliberate: the list path would pay one extra query per row for a field
    // no list renders. A client must not read this null as "not recurring".
    self::assertNull(
      actual: $view->data['recurrence'],
      message: 'The paginated list path must not resolve the recurrence origin.',
    );
  }

  #[Test]
  public function testWorkItemViewMapsRelationsAndAssignee(): void
  {
    $record = $this->entityManager->find(InterventionWorkItemRecord::class, self::WORK_ITEM_ID);
    self::assertInstanceOf(InterventionWorkItemRecord::class, $record);

    $view = $this->mapper->workItemView($record);

    self::assertSame('work_item', $view->resource);
    self::assertSame(self::ORGANIZATION_ID, $view->organizationId);

    $data = $view->data;
    self::assertSame(self::WORK_ITEM_ID, $data['id']);
    self::assertSame('/api/interventions/' . self::INTERVENTION_ID, $data['intervention']);
    self::assertSame('inspect', $data['action']);
    self::assertSame('/api/equipment/abc', $data['target']);
    self::assertNull($data['resultResource']);
    self::assertSame('/api/organizations/' . self::ORGANIZATION_ID . '/members/' . self::ASSIGNEE_ID, $data['assignee']);
    self::assertSame('planned', $data['source']);
    self::assertSame('planned', $data['status']);
    self::assertTrue($data['required']);
    self::assertNull($data['skipReason']);
    self::assertSame(1, $data['revision']);
  }

  #[Test]
  public function testChangeViewMapsWorkItemLinkForBothPresentAndAbsent(): void
  {
    $withWorkItem = $this->entityManager->find(InterventionChangeRecord::class, self::CHANGE_WITH_WORK_ITEM_ID);
    self::assertInstanceOf(InterventionChangeRecord::class, $withWorkItem);

    $view = $this->mapper->changeView($withWorkItem);

    self::assertSame('change', $view->resource);
    self::assertSame(self::ORGANIZATION_ID, $view->organizationId);

    $data = $view->data;
    self::assertSame(self::CHANGE_WITH_WORK_ITEM_ID, $data['id']);
    self::assertSame('/api/interventions/' . self::INTERVENTION_ID, $data['intervention']);
    self::assertSame('/api/intervention-work-items/' . self::WORK_ITEM_ID, $data['workItem']);
    self::assertSame('/api/equipment/abc', $data['resource']);
    self::assertSame(['field' => 'value'], $data['patch']);
    self::assertSame('proposed', $data['status']);

    $withoutWorkItem = $this->entityManager->find(InterventionChangeRecord::class, self::CHANGE_WITHOUT_WORK_ITEM_ID);
    self::assertInstanceOf(InterventionChangeRecord::class, $withoutWorkItem);

    $viewWithout = $this->mapper->changeView($withoutWorkItem);
    self::assertNull($viewWithout->data['workItem']);
    self::assertSame('applied', $viewWithout->data['status']);
  }

  #[Test]
  public function testActivityViewMapsActorAndPayload(): void
  {
    $record = $this->entityManager->find(InterventionActivityRecord::class, self::ACTIVITY_ID);
    self::assertInstanceOf(InterventionActivityRecord::class, $record);

    $view = $this->mapper->activityView($record);

    self::assertSame('activity', $view->resource);
    self::assertSame(self::ORGANIZATION_ID, $view->organizationId);

    $data = $view->data;
    self::assertSame(self::ACTIVITY_ID, $data['id']);
    self::assertSame('/api/interventions/' . self::INTERVENTION_ID, $data['intervention']);
    self::assertSame('comment', $data['kind']);
    self::assertSame('comment', $data['event']);
    self::assertSame('/api/organizations/' . self::ORGANIZATION_ID . '/members/' . self::ACTOR_ID, $data['actor']);
    self::assertSame('First comment', $data['body']);
    self::assertSame(['channel' => 'web'], $data['payload']);
  }

  private function seed(): void
  {
    $timestamp = new DateTimeImmutable('2026-05-01T08:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Intervention View Mapper Test';
    $organization->slug = 'intervention-view-mapper-test';
    $organization->ownerUserId = 'a10e8400-e29b-41d4-a716-446655449000';
    $organization->createdByUserId = 'a10e8400-e29b-41d4-a716-446655449000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $timestamp;
    $organization->updatedAt = $timestamp;
    $this->entityManager->persist($organization);

    $populated = $this->newIntervention(self::INTERVENTION_ID, 1, 'site_setup', $organization, $timestamp);
    $populated->name = 'Annual Fire Panel Inspection';
    $populated->description = 'Full annual inspection.';
    $populated->status = 'review';
    $populated->priority = 'high';
    $populated->siteId = self::SITE_ID;
    $populated->responsibleId = self::RESPONSIBLE_ID;
    $populated->participants = [self::RESPONSIBLE_ID, self::PARTICIPANT_ID];
    $populated->plannedStartAt = new DateTimeImmutable('2026-06-01T09:00:00+00:00');
    $populated->dueAt = new DateTimeImmutable('2026-06-15T17:00:00+00:00');
    $populated->reviewNote = 'Awaiting sign-off.';
    $populated->revision = 3;
    $this->entityManager->persist($populated);

    $inventory = $this->newIntervention(self::INVENTORY_INTERVENTION_ID, 2, 'inventory', $organization, $timestamp);
    $this->entityManager->persist($inventory);

    $minimal = $this->newIntervention(self::MINIMAL_INTERVENTION_ID, 3, 'site_setup', $organization, $timestamp);
    $this->entityManager->persist($minimal);

    // The recurrence chain: a template, the recurrence built on it, an
    // intervention, and the successful run row that links the two. Only the
    // run row carries the link — there is no column on `interventions`.
    $materialized = $this->newIntervention(self::MATERIALIZED_INTERVENTION_ID, 4, 'site_setup', $organization, $timestamp);
    $this->entityManager->persist($materialized);

    $template = new InterventionTemplateRecord();
    $template->id = self::TEMPLATE_ID;
    $template->organization = $organization;
    $template->name = 'Quarterly panel check';
    $template->createdAt = $timestamp;
    $template->updatedAt = $timestamp;
    $this->entityManager->persist($template);

    $recurrence = new InterventionRecurrenceRecord();
    $recurrence->id = self::RECURRENCE_ID;
    $recurrence->organization = $organization;
    $recurrence->template = $template;
    $recurrence->name = 'Quarterly panel check';
    $recurrence->frequency = 'quarterly';
    $recurrence->anchorDate = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $recurrence->nextOccurrenceAt = new DateTimeImmutable('2026-07-01T00:00:00+00:00');
    $recurrence->createdAt = $timestamp;
    $recurrence->updatedAt = $timestamp;
    $this->entityManager->persist($recurrence);

    $run = new InterventionRecurrenceRunRecord();
    $run->id = self::RECURRENCE_RUN_ID;
    $run->recurrence = $recurrence;
    $run->occurrenceDate = new DateTimeImmutable('2026-04-01T00:00:00+00:00');
    $run->status = 'succeeded';
    $run->interventionId = self::MATERIALIZED_INTERVENTION_ID;
    $run->createdAt = $timestamp;
    $this->entityManager->persist($run);

    $label = new InterventionLabelRecord();
    $label->id = self::LABEL_ID;
    $label->organization = $organization;
    $label->name = 'Priority';
    $label->color = '#ff8800';
    $label->createdAt = $timestamp;
    $label->updatedAt = $timestamp;
    $this->entityManager->persist($label);
    $populated->labels->add($label);

    // Populated intervention: two comments + one system event -> commentsCount 2.
    $this->entityManager->persist($this->newActivity(self::ACTIVITY_ID, $populated, self::ACTOR_ID, 'comment', 'comment', 'First comment', ['channel' => 'web'], $timestamp));
    $this->entityManager->persist($this->newActivity('a10e8400-e29b-41d4-a716-446655440202', $populated, null, 'comment', 'comment', 'Second comment', null, $timestamp));
    $this->entityManager->persist($this->newActivity('a10e8400-e29b-41d4-a716-446655440203', $populated, null, 'system', 'created', null, null, $timestamp));

    // Minimal intervention: one comment + one system event -> commentsCount 1.
    $this->entityManager->persist($this->newActivity('a10e8400-e29b-41d4-a716-446655440204', $minimal, self::ACTOR_ID, 'comment', 'comment', 'Solo note', null, $timestamp));
    $this->entityManager->persist($this->newActivity('a10e8400-e29b-41d4-a716-446655440205', $minimal, null, 'system', 'created', null, null, $timestamp));

    $workItem = new InterventionWorkItemRecord();
    $workItem->id = self::WORK_ITEM_ID;
    $workItem->intervention = $populated;
    $workItem->action = 'inspect';
    $workItem->target = '/api/equipment/abc';
    $workItem->assigneeId = self::ASSIGNEE_ID;
    $workItem->source = 'planned';
    $workItem->status = 'planned';
    $workItem->required = true;
    $workItem->revision = 1;
    $workItem->createdAt = $timestamp;
    $workItem->updatedAt = $timestamp;
    $this->entityManager->persist($workItem);

    // Populated intervention carries a completion signature -> hasSignature true.
    $signature = new InterventionAttachmentRecord();
    $signature->id = 'a10e8400-e29b-41d4-a716-446655440210';
    $signature->intervention = $populated;
    $signature->fileName = 'signature.png';
    $signature->storagePath = 'intervention/' . self::INTERVENTION_ID . '/signature.png';
    $signature->mimeType = 'image/png';
    $signature->size = 1024;
    $signature->kind = 'signature';
    $signature->uploadedAt = $timestamp;
    $this->entityManager->persist($signature);

    // Populated intervention: a proposed change linked to the work item and an
    // applied change with no work item (covers both link branches).
    $this->entityManager->persist($this->newChange(self::CHANGE_WITH_WORK_ITEM_ID, $populated, $workItem, 'proposed', $timestamp));
    $this->entityManager->persist($this->newChange(self::CHANGE_WITHOUT_WORK_ITEM_ID, $populated, null, 'applied', $timestamp));

    // Minimal intervention: one proposed + one applied -> proposedChangesCount 1.
    $this->entityManager->persist($this->newChange('a10e8400-e29b-41d4-a716-446655440303', $minimal, null, 'proposed', $timestamp));
    $this->entityManager->persist($this->newChange('a10e8400-e29b-41d4-a716-446655440304', $minimal, null, 'applied', $timestamp));

    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  private function newIntervention(
    string $id,
    int $number,
    string $type,
    OrganizationRecord $organization,
    DateTimeImmutable $timestamp,
  ): InterventionRecord {
    $record = new InterventionRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->type = $type;
    $record->name = 'Intervention ' . $number;
    $record->number = $number;
    $record->status = 'draft';
    $record->priority = 'normal';
    $record->revision = 1;
    $record->createdAt = $timestamp;
    $record->updatedAt = $timestamp;

    return $record;
  }

  /**
   * @param ?array<string, mixed> $payload
   */
  private function newActivity(
    string $id,
    InterventionRecord $intervention,
    ?string $actorId,
    string $kind,
    string $event,
    ?string $body,
    ?array $payload,
    DateTimeImmutable $timestamp,
  ): InterventionActivityRecord {
    $record = new InterventionActivityRecord();
    $record->id = $id;
    $record->intervention = $intervention;
    $record->organizationId = self::ORGANIZATION_ID;
    $record->actorId = $actorId;
    $record->kind = $kind;
    $record->event = $event;
    $record->body = $body;
    $record->payload = $payload;
    $record->createdAt = $timestamp;

    return $record;
  }

  private function newChange(
    string $id,
    InterventionRecord $intervention,
    ?InterventionWorkItemRecord $workItem,
    string $status,
    DateTimeImmutable $timestamp,
  ): InterventionChangeRecord {
    $record = new InterventionChangeRecord();
    $record->id = $id;
    $record->intervention = $intervention;
    $record->workItem = $workItem;
    $record->resource = '/api/equipment/abc';
    $record->patch = ['field' => 'value'];
    $record->status = $status;
    $record->revision = 1;
    $record->createdAt = $timestamp;
    $record->updatedAt = $timestamp;

    return $record;
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM intervention_attachments WHERE intervention_id IN (SELECT id FROM interventions WHERE organization_id = :organizationId)',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM intervention_changes WHERE intervention_id IN (SELECT id FROM interventions WHERE organization_id = :organizationId)',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM intervention_work_items WHERE intervention_id IN (SELECT id FROM interventions WHERE organization_id = :organizationId)',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM intervention_activities WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM intervention_label_assignments WHERE intervention_id IN (SELECT id FROM interventions WHERE organization_id = :organizationId)',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM intervention_labels WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    // Runs first: their FK to the recurrence is ON DELETE CASCADE, but the
    // recurrence itself must go before the template it points at.
    $connection->executeStatement(
      'DELETE FROM intervention_recurrence_runs WHERE recurrence_id IN (SELECT id FROM intervention_recurrences WHERE organization_id = :organizationId)',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM intervention_recurrences WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM intervention_templates WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM interventions WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
