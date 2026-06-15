<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Persistence\Doctrine\Mapper;

use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Resource\InterventionListMetrics;
use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\InterventionIssueFinder;
use Intervention\Domain\Exception\{InterventionConflictException, InterventionNotFoundException};
use Intervention\Infrastructure\Persistence\Doctrine\Record\{
  InterventionChangeRecord,
  InterventionRecord,
  InterventionWorkItemRecord
};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

use function array_filter;
use function array_map;
use function count;
use function in_array;

/**
 * Mapper InterventionViewMapper.
 *
 * Maps intervention persistence records to workflow view DTOs, including the
 * derived collection metrics and resource issues. Extracted from the workflow
 * gateway to keep read-shaping separate from write orchestration.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionViewMapper
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param InterventionResourceGatewayPort $resources the resources value
   * @param InterventionIssueFinder $issueFinder the issue finder value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private InterventionResourceGatewayPort $resources,
    private InterventionIssueFinder $issueFinder,
  ) {
  }

  /**
   * Method interventionView.
   *
   * @since 1.0.0
   *
   * @param InterventionRecord $intervention the intervention value
   * @param ?InterventionListMetrics $metrics preloaded collection metrics
   *
   * @return InterventionWorkflowView the intervention view result
   */
  public function interventionView(InterventionRecord $intervention, ?InterventionListMetrics $metrics = null): InterventionWorkflowView
  {
    $organizationId = $this->organizationId($intervention);
    if (null === $metrics) {
      $summary = $this->resources->summary($intervention->id);
      $workItems = $this->resources->workItemSummary($intervention->id);
      $issues = $this->issueFinder->find(
        $intervention->id,
        $summary,
        $workItems,
        $this->resources->validationContext($intervention->id),
      );
      $facilitiesCount = $summary->facilities;
      $equipmentCount = $summary->equipment;
      $inspectionsCount = $summary->inspections;
      $blockersCount = count(array_filter($issues, static fn ($issue): bool => 'blocker' === $issue->severity));
      $workItemsCount = $workItems->total;
      $completedWorkItemsCount = $workItems->completed;
      $proposedChangesCount = $this->entityManager->getRepository(InterventionChangeRecord::class)->count([
        'intervention' => $intervention,
        'status' => 'proposed',
      ]);
    } else {
      $facilitiesCount = $metrics->facilities;
      $equipmentCount = $metrics->equipment;
      $inspectionsCount = $metrics->inspections;
      $blockersCount = $metrics->resourceBlockers;
      $blockersCount += 'site_setup' === $intervention->type && 0 === $metrics->facilities ? 1 : 0;
      $blockersCount += in_array($intervention->type, ['inventory', 'inspection_campaign'], true) && 0 === $metrics->workItems ? 1 : 0;
      $blockersCount += $metrics->requiredIncomplete > 0 ? 1 : 0;
      $workItemsCount = $metrics->workItems;
      $completedWorkItemsCount = $metrics->completedWorkItems;
      $proposedChangesCount = $metrics->proposedChanges;
    }

    return new InterventionWorkflowView('intervention', $organizationId, [
      'id' => $intervention->id,
      'organization' => '/api/organizations/' . $organizationId,
      'type' => $intervention->type,
      'name' => $intervention->name,
      'status' => $intervention->status,
      'referencePack' => '/api/reference-packs/' . $intervention->referencePackId,
      'site' => null === $intervention->siteId ? null : '/api/facilities/' . $intervention->siteId,
      'responsible' => null === $intervention->responsibleId ? null : '/api/organizations/' . $organizationId . '/members/' . $intervention->responsibleId,
      'participants' => array_map(
        static fn (string $id): string => '/api/organizations/' . $organizationId . '/members/' . $id,
        $intervention->participants,
      ),
      'priority' => $intervention->priority,
      'plannedStartAt' => $intervention->plannedStartAt?->format('c'),
      'dueAt' => $intervention->dueAt?->format('c'),
      'reviewNote' => $intervention->reviewNote,
      'revision' => $intervention->revision,
      'facilitiesCount' => $facilitiesCount,
      'equipmentCount' => $equipmentCount,
      'inspectionsCount' => $inspectionsCount,
      'blockersCount' => $blockersCount,
      'workItemsCount' => $workItemsCount,
      'completedWorkItemsCount' => $completedWorkItemsCount,
      'proposedChangesCount' => $proposedChangesCount,
      'createdAt' => $intervention->createdAt->format('c'),
      'updatedAt' => $intervention->updatedAt->format('c'),
    ]);
  }

  /**
   * Method workItemView.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkItemRecord $record the record value
   *
   * @return InterventionWorkflowView the work item view result
   */
  public function workItemView(InterventionWorkItemRecord $record): InterventionWorkflowView
  {
    $intervention = $this->workItemIntervention($record);
    $organizationId = $this->organizationId($intervention);

    return new InterventionWorkflowView('work_item', $organizationId, [
      'id' => $record->id,
      'intervention' => '/api/interventions/' . $intervention->id,
      'action' => $record->action,
      'target' => $record->target,
      'resultResource' => $record->resultResource,
      'assignee' => null === $record->assigneeId ? null : '/api/organizations/' . $organizationId . '/members/' . $record->assigneeId,
      'source' => $record->source,
      'status' => $record->status,
      'required' => $record->required,
      'skipReason' => $record->skipReason,
      'revision' => $record->revision,
      'createdAt' => $record->createdAt->format('c'),
      'updatedAt' => $record->updatedAt->format('c'),
    ]);
  }

  /**
   * Method changeView.
   *
   * @since 1.0.0
   *
   * @param InterventionChangeRecord $record the record value
   *
   * @return InterventionWorkflowView the change view result
   */
  public function changeView(InterventionChangeRecord $record): InterventionWorkflowView
  {
    $intervention = $this->changeIntervention($record);

    return new InterventionWorkflowView('change', $this->organizationId($intervention), [
      'id' => $record->id,
      'intervention' => '/api/interventions/' . $intervention->id,
      'workItem' => null === $record->workItem ? null : '/api/intervention-work-items/' . $record->workItem->id,
      'resource' => $record->resource,
      'patch' => $record->patch,
      'status' => $record->status,
      'revision' => $record->revision,
      'createdAt' => $record->createdAt->format('c'),
      'updatedAt' => $record->updatedAt->format('c'),
    ]);
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   *
   * @param InterventionRecord $intervention the intervention value
   *
   * @return string the organization id result
   */
  private function organizationId(InterventionRecord $intervention): string
  {
    if (!$intervention->organization instanceof OrganizationRecord) {
      throw new InterventionConflictException('Intervention organization is missing.');
    }

    return $intervention->organization->id;
  }

  /**
   * Method workItemIntervention.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkItemRecord $record the record value
   *
   * @return InterventionRecord the owning intervention record
   */
  private function workItemIntervention(InterventionWorkItemRecord $record): InterventionRecord
  {
    if (!$record->intervention instanceof InterventionRecord) {
      throw InterventionNotFoundException::withId($record->id);
    }

    return $record->intervention;
  }

  /**
   * Method changeIntervention.
   *
   * @since 1.0.0
   *
   * @param InterventionChangeRecord $record the record value
   *
   * @return InterventionRecord the owning intervention record
   */
  private function changeIntervention(InterventionChangeRecord $record): InterventionRecord
  {
    if (!$record->intervention instanceof InterventionRecord) {
      throw InterventionNotFoundException::withId($record->id);
    }

    return $record->intervention;
  }
}
