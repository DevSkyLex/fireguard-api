<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Service;

use Intervention\Application\Contract\Publication\InterventionEconomicContext;
use MaintenanceCost\Application\Contract\Cost\{MaintenanceCostItem, MaintenanceCostView};
use MaintenanceCost\Application\Contract\Reporting\MaintenanceEconomicReport;
use MaintenanceCost\Application\UseCase\Query\Reporting\ReadMaintenanceEconomicReport\ReadMaintenanceEconomicReportQuery;
use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use Procurement\Application\Contract\Reporting\ProcurementEconomicOverview;

use function array_map;
use function array_slice;
use function bcsub;
use function count;

/**
 * Class MaintenanceEconomicAggregator
 *
 * Allocates every contribution once. Global facts and missing historic target
 * identities remain unallocated; filtered-out targets reconcile separately.
 *
 * @category Service
 */
final readonly class MaintenanceEconomicAggregator
{
  /**
   * @param list<InterventionEconomicContext> $contexts bounded source dossiers
   * @param array<string,MaintenanceCostView> $views scoped private facts keyed by intervention
   */
  public function report(ReadMaintenanceEconomicReportQuery $query, string $currency, array $contexts, array $views, ProcurementEconomicOverview $procurement): MaintenanceEconomicReport
  {
    $contexts = $this->eligibleContexts($query, $currency, $contexts, $views);
    $aggregation = new MaintenanceEconomicAggregation();
    $published = 0;
    $missing = 0;
    $factCount = 0;
    foreach ($contexts as $context) {
      $view = $views[$context->id] ?? throw MaintenanceCostException::notFound();
      $this->assertSource($query, $currency, $context, $view);
      $isPublished = 'published' === $context->status;
      $published += $isPublished ? 1 : 0;
      $missing += $isPublished && ('snapshot_missing' === $context->snapshotState || null === $view->frozen) ? 1 : 0;
      $this->retainActualCosts($query, $currency, $context, $view, $aggregation, $factCount);
      $this->retainPlanning($query, $currency, $context, $view, $aggregation, $factCount);
    }
    $rows = $aggregation->rows();
    $current = $aggregation->amount('current');
    $planned = $aggregation->amount('planned');

    return new MaintenanceEconomicReport($query->organizationId, $query->from, $query->to, $query->groupBy, $currency, $query->page, $query->itemsPerPage, count($rows), count($contexts), $published, count($contexts) - $published, $missing, array_slice($rows, ($query->page - 1) * $query->itemsPerPage, $query->itemsPerPage), $aggregation->unallocated->row(), $current, $aggregation->amount('frozen'), $planned, $aggregation->amount('budget'), $current->complete && $planned->complete && $planned->contributionCount > 0 ? bcsub($current->knownTotal, $planned->knownTotal, 6) : null, $aggregation->reconciliation(), $procurement, array_map(static fn (InterventionEconomicContext $context): array => ['id' => $context->id, 'number' => $context->number, 'name' => $context->name, 'status' => $context->status, 'snapshotState' => $context->snapshotState], $contexts));
  }

  /**
   * Method eligibleContexts
   *
   * Authorizes every candidate before financial target selection.
   *
   * @access private
   *
   * @param ReadMaintenanceEconomicReportQuery $query authorized report scope
   * @param string $currency organization currency
   * @param list<InterventionEconomicContext> $contexts bounded candidate dossiers
   * @param array<string,MaintenanceCostView> $views private facts by dossier identity
   *
   * @return list<InterventionEconomicContext> dossiers retained by source target identities
   */
  private function eligibleContexts(ReadMaintenanceEconomicReportQuery $query, string $currency, array $contexts, array $views): array
  {
    $eligible = [];
    foreach ($contexts as $context) {
      $view = $views[$context->id] ?? throw MaintenanceCostException::notFound();
      $this->assertSource($query, $currency, $context, $view);
      if ($this->eligible($query, $context, $view)) {
        $eligible[] = $context;
      }
    }

    return $eligible;
  }

  /**
   * Method assertSource
   *
   * Keeps operational and private source facts inside one authorized organization currency.
   *
   * @access private
   *
   * @param ReadMaintenanceEconomicReportQuery $query authorized organization scope
   * @param string $currency organization currency
   * @param InterventionEconomicContext $context operational source
   * @param MaintenanceCostView $view private source
   *
   * @return void
   */
  private function assertSource(ReadMaintenanceEconomicReportQuery $query, string $currency, InterventionEconomicContext $context, MaintenanceCostView $view): void
  {
    if ($view->organizationId !== $query->organizationId || $context->organizationId !== $query->organizationId || $view->currency !== $currency) {
      throw MaintenanceCostException::conflict('Economic sources do not share one authorized organization currency.');
    }
  }

  /**
   * Method retainActualCosts
   *
   * Values current and captured facts separately, including an explicit unknown for a missing private publication.
   *
   * @access private
   *
   * @param ReadMaintenanceEconomicReportQuery $query selected target scope
   * @param string $currency organization currency
   * @param InterventionEconomicContext $context source allocation identities
   * @param MaintenanceCostView $view current and captured financial facts
   * @param MaintenanceEconomicAggregation $aggregation report accumulation state
   * @param int $factCount contributions retained across every source
   *
   * @return void
   */
  private function retainActualCosts(ReadMaintenanceEconomicReportQuery $query, string $currency, InterventionEconomicContext $context, MaintenanceCostView $view, MaintenanceEconomicAggregation $aggregation, int &$factCount): void
  {
    foreach (['current' => $view->current->items, 'frozen' => $view->frozen?->totals->items ?? []] as $kind => $items) {
      foreach ($items as $item) {
        if ($item->currency !== $currency) {
          throw MaintenanceCostException::conflict('A contribution uses another organization currency.');
        }
        $this->retain($kind, $item->amount, $this->allocation($item, $context), $context->id, $query, $aggregation);
        $this->bounded(++$factCount);
      }
    }
    if ('published' === $context->status && null === $view->frozen) {
      $this->retain('frozen', null, $this->emptyAllocation(), $context->id, $query, $aggregation);
    }
  }

  /**
   * Method retainPlanning
   *
   * Published preparation remains frozen; resources replace the budget baseline rather than adding it twice.
   *
   * @access private
   *
   * @param ReadMaintenanceEconomicReportQuery $query selected target scope
   * @param string $currency organization currency
   * @param InterventionEconomicContext $context source allocation identities
   * @param MaintenanceCostView $view live and captured preparation
   * @param MaintenanceEconomicAggregation $aggregation report accumulation state
   * @param int $factCount contributions retained across every source
   *
   * @return void
   */
  private function retainPlanning(ReadMaintenanceEconomicReportQuery $query, string $currency, InterventionEconomicContext $context, MaintenanceCostView $view, MaintenanceEconomicAggregation $aggregation, int &$factCount): void
  {
    $isPublished = 'published' === $context->status;
    $planning = $isPublished && null !== $view->frozen?->planning ? $view->frozen->planning : $view->planning;
    $aggregation->budget($planning->plannedBudget, $context->id, $isPublished);
    if ([] === $planning->resources) {
      $this->retain('planned', $planning->plannedBudget, $this->emptyAllocation(), $context->id, $query, $aggregation);

      return;
    }
    foreach ($planning->resources as $resource) {
      $item = new MaintenanceCostItem('planning', 'planning', $resource['workItemId'], 'planning', null, $resource['amount'], $currency, $resource['description'], '');
      $this->retain('planned', $resource['amount'], $this->allocation($item, $context), $context->id, $query, $aggregation);
      $this->bounded(++$factCount);
    }
  }

  /**
   * @return array{identityState:string,equipment:?array{id:string,name:?string,assetReference:?string},site:?array{id:string,name:string},customer:?array{id:string,name:string}}
   */
  private function allocation(MaintenanceCostItem $item, InterventionEconomicContext $context): array
  {
    if (null !== $item->allocation) {
      return $item->allocation;
    }
    $task = null;
    foreach ($context->workItems as $candidate) {
      if (null !== $item->workItemId && $candidate->id === $item->workItemId) {
        $task = $candidate;

        break;
      }
      if (null === $item->workItemId && null !== $item->equipmentId && $candidate->equipmentId === $item->equipmentId) {
        if (null !== $task) {
          $task = null;

          break;
        }
        $task = $candidate;
      }
    }
    $id = $item->equipmentId ?? $task?->equipmentId;
    $identity = $task?->equipmentIdentity;

    $state = null !== $identity ? 'captured' : 'incomplete';
    if ('live' === $context->snapshotState) {
      $state = 'live';
    }

    return ['identityState' => $state, 'equipment' => null === $id ? null : ['id' => $id, 'name' => $identity?->name, 'assetReference' => $identity?->assetReference], 'site' => $task?->site, 'customer' => $task?->customer];
  }

  /**
   * @param array{identityState:string,equipment:?array{id:string,name:?string,assetReference:?string},site:?array{id:string,name:string},customer:?array{id:string,name:string}} $allocation
   * @param MaintenanceEconomicAggregation $aggregation related bucket and reconciliation state
   */
  private function retain(string $kind, ?string $amount, array $allocation, string $interventionId, ReadMaintenanceEconomicReportQuery $query, MaintenanceEconomicAggregation $aggregation): void
  {
    $identity = match ($query->groupBy) {
      'equipment' => $allocation['equipment'],
      'site' => $allocation['site'],
      'customer' => $allocation['customer'],
      default => throw MaintenanceCostException::invalid('Unknown economic grouping.'),
    };
    $aggregation->retain($kind, $amount, $allocation, $interventionId, $identity, $this->matches($query, $allocation));
  }

  /**
   * @return array{identityState:string,equipment:null,site:null,customer:null}
   */
  private function emptyAllocation(): array
  {
    return ['identityState' => 'incomplete', 'equipment' => null, 'site' => null, 'customer' => null];
  }

  private function bounded(int $count): void
  {
    if ($count > 50000) {
      throw MaintenanceCostException::invalid('The report exceeds 50000 contributions; narrow its dates or target filters.');
    }
  }

  /**
   * @param array{identityState:string,equipment:?array{id:string,name:?string,assetReference:?string},site:?array{id:string,name:string},customer:?array{id:string,name:string}} $allocation
   *
   * @return ?bool true matching target, false known different target, null unresolved scope
   */
  private function matches(ReadMaintenanceEconomicReportQuery $query, array $allocation): ?bool
  {
    $unknown = false;
    foreach (['equipment' => $query->equipmentId, 'site' => $query->siteId, 'customer' => $query->customerId] as $key => $filter) {
      if (null === $filter) {
        continue;
      }
      $actual = $allocation[$key]['id'] ?? null;
      if (null === $actual) {
        $unknown = true;
      } elseif ($actual !== $filter) {
        return false;
      }
    }

    return $unknown ? null : true;
  }

  private function eligible(ReadMaintenanceEconomicReportQuery $query, InterventionEconomicContext $context, MaintenanceCostView $view): bool
  {
    if (null === $query->equipmentId && null === $query->siteId && null === $query->customerId) {
      return true;
    }
    $items = [...$view->current->items, ...($view->frozen?->totals->items ?? [])];
    if ($this->hasEligibleTarget($query, $context, $items)) {
      return true;
    }
    $taskTargets = [];
    foreach ($context->workItems as $task) {
      $taskTargets[] = new MaintenanceCostItem('target', 'target', $task->id, 'target', null, null, $view->currency, '', '');
    }
    $root = ['identityState' => 'live' === $context->snapshotState ? 'live' : 'captured', 'equipment' => null, 'site' => $context->site, 'customer' => $context->customer];

    return $this->hasEligibleTarget($query, $context, $taskTargets) || (null === $query->equipmentId && true === $this->matches($query, $root));
  }

  /**
   * Method hasEligibleTarget
   *
   * Unknown filter dimensions remain eligible only when the source has at least one target identity.
   *
   * @access private
   *
   * @param ReadMaintenanceEconomicReportQuery $query selected target scope
   * @param InterventionEconomicContext $context captured task fallback identities
   * @param list<MaintenanceCostItem> $items financial facts or minimal operational target placeholders
   *
   * @return bool whether any source target can satisfy the selection
   */
  private function hasEligibleTarget(ReadMaintenanceEconomicReportQuery $query, InterventionEconomicContext $context, array $items): bool
  {
    foreach ($items as $item) {
      $allocation = $this->allocation($item, $context);
      $hasTarget = null !== $allocation['equipment'] || null !== $allocation['site'] || null !== $allocation['customer'];
      if ($hasTarget && false !== $this->matches($query, $allocation)) {
        return true;
      }
    }

    return false;
  }
}
