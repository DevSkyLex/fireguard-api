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
use function bcadd;
use function bccomp;
use function bcsub;
use function count;
use function usort;

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
    $eligibleContexts = [];
    foreach ($contexts as $context) {
      $view = $views[$context->id] ?? throw MaintenanceCostException::notFound();
      if ($view->organizationId !== $query->organizationId || $context->organizationId !== $query->organizationId || $view->currency !== $currency) {
        throw MaintenanceCostException::conflict('Economic sources do not share one authorized organization currency.');
      }
      if ($this->eligible($query, $context, $view)) {
        $eligibleContexts[] = $context;
      }
    }
    $contexts = $eligibleContexts;
    $buckets = [];
    $unallocated = new MaintenanceEconomicBucket(null, null);
    $selected = ['current' => new MaintenanceEconomicAccumulator(), 'frozen' => new MaintenanceEconomicAccumulator(), 'planned' => new MaintenanceEconomicAccumulator(), 'budget' => new MaintenanceEconomicAccumulator()];
    $source = ['current' => new MaintenanceEconomicAccumulator(), 'frozen' => new MaintenanceEconomicAccumulator(), 'planned' => new MaintenanceEconomicAccumulator()];
    $excluded = ['current' => new MaintenanceEconomicAccumulator(), 'frozen' => new MaintenanceEconomicAccumulator(), 'planned' => new MaintenanceEconomicAccumulator()];
    $published = 0;
    $missing = 0;
    $factCount = 0;
    foreach ($contexts as $context) {
      $view = $views[$context->id] ?? throw MaintenanceCostException::notFound();
      if ($view->organizationId !== $query->organizationId || $context->organizationId !== $query->organizationId || $view->currency !== $currency) {
        throw MaintenanceCostException::conflict('Economic sources do not share one authorized organization currency.');
      }
      $isPublished = 'published' === $context->status;
      $published += $isPublished ? 1 : 0;
      $missing += $isPublished && ('snapshot_missing' === $context->snapshotState || null === $view->frozen) ? 1 : 0;
      foreach (['current' => $view->current->items, 'frozen' => $view->frozen?->totals->items ?? []] as $kind => $items) {
        foreach ($items as $item) {
          if ($item->currency !== $currency) {
            throw MaintenanceCostException::conflict('A contribution uses another organization currency.');
          }
          $allocation = $this->allocation($item, $context);
          $this->retain($kind, $item->amount, $allocation, $context->id, $query, $buckets, $unallocated, $selected, $source, $excluded);
          $this->bounded(++$factCount);
        }
      }
      if ($isPublished && null === $view->frozen) {
        $this->retain('frozen', null, $this->emptyAllocation(), $context->id, $query, $buckets, $unallocated, $selected, $source, $excluded);
      }
      $planning = $isPublished && null !== $view->frozen?->planning ? $view->frozen->planning : $view->planning;
      $unallocated->budget->add($planning->plannedBudget);
      $selected['budget']->add($planning->plannedBudget);
      $unallocated->source($context->id, $isPublished ? 'captured' : 'live', false, null);
      if ([] === $planning->resources) {
        $this->retain('planned', $planning->plannedBudget, $this->emptyAllocation(), $context->id, $query, $buckets, $unallocated, $selected, $source, $excluded);
      } else {
        foreach ($planning->resources as $resource) {
          $item = new MaintenanceCostItem('planning', 'planning', $resource['workItemId'], 'planning', null, $resource['amount'], $currency, $resource['description'], '');
          $this->retain('planned', $resource['amount'], $this->allocation($item, $context), $context->id, $query, $buckets, $unallocated, $selected, $source, $excluded);
          $this->bounded(++$factCount);
        }
      }
    }
    $rows = [];
    foreach ($buckets as $bucket) {
      $rows[] = $bucket->row();
    }
    usort($rows, static fn ($a, $b): int => ($a->name ?? $a->id ?? '') <=> ($b->name ?? $b->id ?? ''));
    $current = $selected['current']->amount();
    $planned = $selected['planned']->amount();
    $reconciled = true;
    foreach (['current', 'frozen', 'planned'] as $kind) {
      $includedAmount = $selected[$kind]->amount();
      $excludedAmount = $excluded[$kind]->amount();
      $sourceAmount = $source[$kind]->amount();
      $sum = bcadd($includedAmount->knownTotal, $excludedAmount->knownTotal, 6);
      $reconciled = $reconciled && 0 === bccomp($sum, $sourceAmount->knownTotal, 6)
        && $includedAmount->contributionCount + $excludedAmount->contributionCount === $sourceAmount->contributionCount
        && $includedAmount->unknownCount + $excludedAmount->unknownCount === $sourceAmount->unknownCount;
    }

    return new MaintenanceEconomicReport($query->organizationId, $query->from, $query->to, $query->groupBy, $currency, $query->page, $query->itemsPerPage, count($rows), count($contexts), $published, count($contexts) - $published, $missing, array_slice($rows, ($query->page - 1) * $query->itemsPerPage, $query->itemsPerPage), $unallocated->row(), $current, $selected['frozen']->amount(), $planned, $selected['budget']->amount(), $current->complete && $planned->complete && $planned->contributionCount > 0 ? bcsub($current->knownTotal, $planned->knownTotal, 6) : null, ['sourceCurrent' => $source['current']->amount(), 'excludedCurrent' => $excluded['current']->amount(), 'sourceFrozen' => $source['frozen']->amount(), 'excludedFrozen' => $excluded['frozen']->amount(), 'sourcePlanned' => $source['planned']->amount(), 'excludedPlanned' => $excluded['planned']->amount(), 'reconciled' => $reconciled], $procurement, array_map(static fn (InterventionEconomicContext $context): array => ['id' => $context->id, 'number' => $context->number, 'name' => $context->name, 'status' => $context->status, 'snapshotState' => $context->snapshotState], $contexts));
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

    return ['identityState' => 'live' === $context->snapshotState ? 'live' : (null !== $identity ? 'captured' : 'incomplete'), 'equipment' => null === $id ? null : ['id' => $id, 'name' => $identity?->name, 'assetReference' => $identity?->assetReference], 'site' => $task?->site, 'customer' => $task?->customer];
  }

  /**
   * @param array{identityState:string,equipment:?array{id:string,name:?string,assetReference:?string},site:?array{id:string,name:string},customer:?array{id:string,name:string}} $allocation
   * @param array<string,MaintenanceEconomicBucket> $buckets
   * @param array<string,MaintenanceEconomicAccumulator> $selected
   * @param array<string,MaintenanceEconomicAccumulator> $source
   * @param array<string,MaintenanceEconomicAccumulator> $excluded
   */
  private function retain(string $kind, ?string $amount, array $allocation, string $interventionId, ReadMaintenanceEconomicReportQuery $query, array &$buckets, MaintenanceEconomicBucket $unallocated, array $selected, array $source, array $excluded): void
  {
    $source[$kind]->add($amount);
    $identity = match ($query->groupBy) {
      'equipment' => $allocation['equipment'],
      'site' => $allocation['site'],
      'customer' => $allocation['customer'],
      default => throw MaintenanceCostException::invalid('Unknown economic grouping.'),
    };
    $hasAllocation = null !== $allocation['equipment'] || null !== $allocation['site'] || null !== $allocation['customer'];
    $matches = $this->matches($query, $allocation);
    if ($hasAllocation && false === $matches) {
      $excluded[$kind]->add($amount);

      return;
    }
    if (null === $matches) {
      $identity = null;
    }
    $selected[$kind]->add($amount);
    if (null === $identity) {
      $bucket = $unallocated;
    } else {
      $bucket = $buckets[$identity['id']] ??= new MaintenanceEconomicBucket($identity['id'], $identity['name']);
    }
    $target = match ($kind) {
      'current' => $bucket->current,
      'frozen' => $bucket->frozen,
      'planned' => $bucket->planned,
      default => throw MaintenanceCostException::invalid('Unknown economic contribution.'),
    };
    $target->add($amount);
    $bucket->source($interventionId, $allocation['identityState'], null !== $identity && 'incomplete' !== $allocation['identityState'], $identity['name'] ?? null);
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
    foreach ([...$view->current->items, ...($view->frozen?->totals->items ?? [])] as $item) {
      $allocation = $this->allocation($item, $context);
      $hasTarget = null !== $allocation['equipment'] || null !== $allocation['site'] || null !== $allocation['customer'];
      if ($hasTarget && false !== $this->matches($query, $allocation)) {
        return true;
      }
    }
    foreach ($context->workItems as $task) {
      $item = new MaintenanceCostItem('target', 'target', $task->id, 'target', null, null, $view->currency, '', '');
      $allocation = $this->allocation($item, $context);
      $hasTarget = null !== $allocation['equipment'] || null !== $allocation['site'] || null !== $allocation['customer'];
      if ($hasTarget && false !== $this->matches($query, $allocation)) {
        return true;
      }
    }
    $root = ['identityState' => 'live' === $context->snapshotState ? 'live' : 'captured', 'equipment' => null, 'site' => $context->site, 'customer' => $context->customer];

    return null === $query->equipmentId && true === $this->matches($query, $root);
  }
}
