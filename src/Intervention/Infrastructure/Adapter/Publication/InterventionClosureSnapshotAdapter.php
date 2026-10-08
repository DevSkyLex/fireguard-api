<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Publication;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Publication\{InterventionEquipmentSnapshot, InterventionFactsScopeTooLarge};
use Intervention\Application\Port\Outbound\{InterventionEquipmentSnapshotPort, InterventionInspectionResultPort, InterventionMemberNamingPort, InterventionSiteCustomerSnapshotPort};
use Intervention\Infrastructure\Persistence\Doctrine\Mapper\InterventionPublicationFactsMapper;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionActivityRecord, InterventionAttachmentRecord, InterventionChangeRecord, InterventionRecord, InterventionTimeEntryRecord, InterventionWorkItemRecord};

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function get_object_vars;
use function is_string;
use function preg_match;

/**
 * Class InterventionClosureSnapshotAdapter
 *
 * Captures a versioned dossier inside publication without freezing or mutating the independent time journal.
 *
 * @category Adapter
 */
final readonly class InterventionClosureSnapshotAdapter
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager owning main manager
   * @param InterventionMemberNamingPort $members member identity snapshot reader
   * @param InterventionSiteCustomerSnapshotPort $sites site and internal customer snapshot reader
   * @param InterventionEquipmentSnapshotPort $equipment same-organization asset identity reader
   * @param InterventionPublicationFactsMapper $facts stable target decoder
   * @param InterventionInspectionResultPort $inspections owner-validated control result reader
   *
   * @return void
   */
  public function __construct(private EntityManagerInterface $entityManager, private InterventionMemberNamingPort $members, private InterventionSiteCustomerSnapshotPort $sites, private InterventionEquipmentSnapshotPort $equipment, private InterventionPublicationFactsMapper $facts, private InterventionInspectionResultPort $inspections)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method capture
   *
   * Returns the original dossier on replay; publication owns the order lock and durable save.
   *
   * @access public
   *
   * @param InterventionRecord $intervention locked intervention with published resources
   * @param string $organizationId owning organization
   * @param ?string $publicationId original publication identifier
   * @param ?DateTimeImmutable $publishedAt original completed publication instant
   *
   * @return array<string,mixed> immutable versioned dossier
   */
  public function capture(InterventionRecord $intervention, string $organizationId, ?string $publicationId = null, ?DateTimeImmutable $publishedAt = null): array
  {
    if (null !== $intervention->closureSnapshot) {
      return $intervention->closureSnapshot;
    }
    $items = $this->entityManager->getRepository(InterventionWorkItemRecord::class)->findBy(['intervention' => $intervention], ['createdAt' => 'ASC', 'id' => 'ASC'], 10001);
    $attachments = $this->entityManager->getRepository(InterventionAttachmentRecord::class)->findBy(['intervention' => $intervention], ['uploadedAt' => 'ASC', 'id' => 'ASC'], 10001);
    $activities = $this->entityManager->getRepository(InterventionActivityRecord::class)->findBy(['intervention' => $intervention], ['createdAt' => 'ASC', 'id' => 'ASC'], 10001);
    $changes = $this->entityManager->getRepository(InterventionChangeRecord::class)->findBy(['intervention' => $intervention], null, 10001);
    /** @var list<InterventionTimeEntryRecord> $times */
    $times = $this->entityManager->createQueryBuilder()->select('t')->from(InterventionTimeEntryRecord::class, 't')->join('t.workItem', 'w')->where('w.intervention = :intervention AND t.organizationId = :organization')->setParameter('intervention', $intervention)->setParameter('organization', $organizationId)->orderBy('t.id', 'ASC')->setMaxResults(10001)->getQuery()->getResult();
    $this->assertSourcesBounded([$items, $attachments, $activities, $changes, $times]);
    $equipmentSnapshots = $this->equipmentSnapshots($organizationId, $items);
    $memberIds = [$intervention->responsibleId, ...$intervention->participants, ...array_map(static fn (InterventionWorkItemRecord $item): ?string => $item->assigneeId, $items), ...array_map(static fn (InterventionActivityRecord $activity): ?string => $activity->actorId, $activities)];
    $names = $this->members->displayNamesFor($organizationId, array_values(array_unique(array_filter($memberIds, is_string(...)))));
    $identity = $this->sites->snapshot($organizationId, $intervention->siteId);
    $site = ['site' => $identity['site'], 'customer' => null === $identity['customer'] ? null : ['id' => $identity['customer']['id'], 'name' => $identity['customer']['name']]];
    $capturedAt = ($publishedAt ?? new DateTimeImmutable())->format('c');
    $spentMinutes = $this->spentMinutes($times);
    $evidenceCounts = $this->evidenceCounts($attachments);
    $workItems = array_map(fn (InterventionWorkItemRecord $item): array => $this->workItem($item, $organizationId, $spentMinutes[$item->id] ?? 0, $evidenceCounts[$item->id] ?? 0, $equipmentSnapshots[$this->facts->equipmentId($item->target) ?? ''] ?? null, $equipmentSnapshots[$this->facts->equipmentId($item->resultResource) ?? ''] ?? null, $capturedAt), $items);
    $evidence = array_map(static fn (InterventionAttachmentRecord $attachment): array => ['id' => $attachment->id, 'fileName' => $attachment->fileName, 'kind' => $attachment->kind, 'mimeType' => $attachment->mimeType, 'size' => $attachment->size, 'label' => $attachment->label, 'workItemId' => $attachment->workItem?->id, 'revision' => $attachment->revision, 'uploadedAt' => $attachment->uploadedAt->format('c')], $attachments);
    $timeEntries = array_map(static fn (InterventionTimeEntryRecord $entry): array => ['id' => $entry->id, 'workItemId' => $entry->workItem?->id, 'memberId' => $entry->memberId, 'workedOn' => $entry->workedOn, 'minutes' => $entry->minutes, 'note' => $entry->note, 'cancelled' => $entry->cancelled, 'revision' => $entry->revision], $times);
    $report = ['number' => $intervention->number, 'name' => $intervention->name, 'type' => $intervention->type, 'status' => 'published', 'priority' => $intervention->priority, 'siteName' => $site['site']['name'] ?? null, 'customerName' => $site['customer']['name'] ?? null, 'responsibleName' => $names[$intervention->responsibleId ?? ''] ?? null, 'participantNames' => array_values(array_filter(array_map(static fn (string $id): ?string => $names[$id] ?? null, $intervention->participants))), 'plannedStartAt' => $intervention->plannedStartAt?->format('c'), 'dueAt' => $intervention->dueAt?->format('c'), 'reviewNote' => $intervention->reviewNote, 'hasSignature' => count(array_filter($attachments, static fn (InterventionAttachmentRecord $item): bool => 'signature' === $item->kind)) > 0, 'labels' => array_map(static fn ($label): array => ['id' => $label->id, 'name' => $label->name, 'color' => $label->color], $intervention->labels->toArray()), 'generatedAt' => $capturedAt, 'reportMode' => 'snapshot', 'capturedAt' => $capturedAt, 'snapshotVersion' => 2, 'workItems' => [], 'issues' => [], 'proposedChangesCount' => 0, 'appliedChangesCount' => 0, 'rejectedChangesCount' => 0, 'attachments' => $evidence, 'activities' => [], 'timeEntries' => $timeEntries];
    $reportItems = [];
    foreach ($items as $index => $item) {
      $reportItems[] = [...$workItems[$index], 'assigneeName' => $names[$item->assigneeId ?? ''] ?? null];
    }
    $report['workItems'] = $reportItems;
    $changeCounts = ['proposedChangesCount' => 0, 'appliedChangesCount' => 0, 'rejectedChangesCount' => 0];
    foreach ($changes as $change) {
      $key = $change->status . 'ChangesCount';
      $changeCounts[$key] = ($changeCounts[$key] ?? 0) + 1;
    }
    $report = [...$report, ...$changeCounts];
    $reportActivities = [];
    foreach ($activities as $activity) {
      $reportActivities[] = ['createdAt' => $activity->createdAt->format('c'), 'kind' => $activity->kind, 'event' => $activity->event, 'body' => $activity->body, 'payload' => $activity->payload, 'actorName' => $names[$activity->actorId ?? ''] ?? null];
    }
    $report['activities'] = $reportActivities;

    return ['version' => 2, 'capturedAt' => $capturedAt, 'publishedAt' => $capturedAt, 'publicationId' => $publicationId, 'interventionId' => $intervention->id, 'revision' => $intervention->revision + 1, 'number' => $intervention->number, 'name' => $intervention->name, 'type' => $intervention->type, 'createdAt' => $intervention->createdAt->format('c'), 'plannedStartAt' => $intervention->plannedStartAt?->format('c'), 'dueAt' => $intervention->dueAt?->format('c'), ...$site, 'memberNames' => $names, 'workItems' => $workItems, 'attachments' => $evidence, 'timeEntries' => $timeEntries, 'report' => $report];
  }

  /**
   * Method assertSourcesBounded
   *
   * Refuses oversized sources before resolving owner identities or constructing the dossier.
   *
   * @access private
   *
   * @param list<list<object>> $sources loaded owned record collections
   *
   * @return void
   */
  private function assertSourcesBounded(array $sources): void
  {
    foreach ($sources as $records) {
      if (count($records) > 10000) {
        throw new InterventionFactsScopeTooLarge('A publication dossier exceeds 10000 records in one source; split the work before publication.');
      }
    }
  }

  /**
   * Method equipmentSnapshots
   *
   * Resolves original targets and replacement successors together through the equipment owner's public bridge.
   *
   * @access private
   *
   * @param string $organizationId owning organization
   * @param list<InterventionWorkItemRecord> $items bounded reviewed tasks
   *
   * @return array<string,InterventionEquipmentSnapshot> owner-supplied immutable equipment identity
   */
  private function equipmentSnapshots(string $organizationId, array $items): array
  {
    $ids = [];
    foreach ($items as $item) {
      foreach ([$item->target, $item->resultResource] as $target) {
        $id = $this->facts->equipmentId($target);
        if (null !== $id) {
          $ids[] = $id;
        }
      }
    }

    return $this->equipment->snapshots($organizationId, array_values(array_unique($ids)));
  }

  /**
   * Method spentMinutes
   *
   * Captures only uncancelled entries without modifying the independent journal.
   *
   * @access private
   *
   * @param list<InterventionTimeEntryRecord> $times bounded source entries
   *
   * @return array<string,int> whole minutes grouped by task
   */
  private function spentMinutes(array $times): array
  {
    $minutes = [];
    foreach ($times as $entry) {
      if (!$entry->cancelled && null !== $entry->workItem) {
        $minutes[$entry->workItem->id] = ($minutes[$entry->workItem->id] ?? 0) + $entry->minutes;
      }
    }

    return $minutes;
  }

  /**
   * Method evidenceCounts
   *
   * Counts task-linked proof without including intervention-only attachments.
   *
   * @access private
   *
   * @param list<InterventionAttachmentRecord> $attachments bounded source evidence
   *
   * @return array<string,int> evidence counts grouped by task
   */
  private function evidenceCounts(array $attachments): array
  {
    $counts = [];
    foreach ($attachments as $attachment) {
      if (null !== $attachment->workItem) {
        $counts[$attachment->workItem->id] = ($counts[$attachment->workItem->id] ?? 0) + 1;
      }
    }

    return $counts;
  }

  /**
   * Method workItem
   *
   * Maps the dossier from loaded records and grouped evidence/time totals without per-task queries.
   *
   * @access private
   *
   * @param InterventionWorkItemRecord $item reviewed task
   * @param string $organizationId owning organization
   * @param int $spentMinutes known uncancelled time in whole minutes
   * @param int $evidenceCount known attached proofs
   * @param ?InterventionEquipmentSnapshot $equipment captured target asset identity
   * @param ?InterventionEquipmentSnapshot $resultEquipment captured replacement successor identity
   * @param string $capturedAt publication timestamp
   *
   * @return array<string,mixed> frozen work item read shape
   */
  private function workItem(InterventionWorkItemRecord $item, string $organizationId, int $spentMinutes, int $evidenceCount, ?InterventionEquipmentSnapshot $equipment, ?InterventionEquipmentSnapshot $resultEquipment, string $capturedAt): array
  {
    $execution = $item->executionResult;
    if ('inspection' === $item->action && 'completed' === $item->status && null === $execution && null !== $item->resultResource && 1 === preg_match('#^/api/inspections/([^/]+)$#', $item->resultResource, $match) && null !== $item->intervention) {
      $inspection = $this->inspections->find($organizationId, $item->intervention->id, $match[1]);
      if (null !== $inspection && 'closed' === $inspection->status && $inspection->equipmentId === $this->facts->equipmentId($item->target)) {
        $execution = ['equipmentId' => $inspection->equipmentId, 'performedAt' => $inspection->performedAt->format('c'), 'outcome' => 'performed', 'inspectionResult' => $inspection->result, 'workPerformed' => $inspection->notes ?? '', 'authorId' => $inspection->authorId, 'state' => 'validated', 'validatedAt' => $capturedAt];
      }
    }

    return [
      'id' => $item->id,
      'intervention' => '/api/interventions/' . $item->intervention?->id,
      'action' => $item->action,
      'target' => $item->target,
      'resultResource' => $item->resultResource,
      'assignee' => null === $item->assigneeId ? null : '/api/organizations/' . $organizationId . '/members/' . $item->assigneeId,
      'source' => $item->source,
      'status' => $item->status,
      'required' => $item->required,
      'skipReason' => $item->skipReason,
      'operationId' => $item->operationId,
      'occurrenceId' => $item->occurrenceId,
      'operationKind' => $item->operationKind,
      'executionResult' => $execution,
      'equipmentIdentity' => null === $equipment ? null : get_object_vars($equipment),
      'resultEquipmentIdentity' => null === $resultEquipment ? null : get_object_vars($resultEquipment),
      'site' => $equipment?->site,
      'customer' => $equipment?->customer,
      'estimatedMinutes' => $item->estimatedMinutes,
      'remainingMinutes' => $item->remainingMinutes,
      'workStartsOn' => $item->workStartsOn,
      'workEndsOn' => $item->workEndsOn,
      'spentMinutes' => $spentMinutes,
      'evidenceCount' => $evidenceCount,
      'revision' => $item->revision,
      'createdAt' => $item->createdAt->format('c'),
      'updatedAt' => $item->updatedAt->format('c'),
    ];
  }
  // #endregion
}
