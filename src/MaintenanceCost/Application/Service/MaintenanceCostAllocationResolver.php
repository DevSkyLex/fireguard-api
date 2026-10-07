<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Service;

use Intervention\Application\Port\Inbound\InterventionPublicationFactsPort;
use Intervention\Application\Port\Outbound\InterventionEquipmentSnapshotPort;
use MaintenanceCost\Application\Contract\Cost\MaintenanceCostItem;
use MaintenanceCost\Domain\Exception\MaintenanceCostException;

use function array_key_exists;
use function array_keys;

/**
 * Class MaintenanceCostAllocationResolver
 *
 * Resolves current contribution identities through owning public bridges and
 * preserves the captured identity of an already published source.
 *
 * @category Service
 */
final readonly class MaintenanceCostAllocationResolver
{
  public function __construct(private InterventionPublicationFactsPort $work, private InterventionEquipmentSnapshotPort $equipment)
  {
  }

  /**
   * @param list<MaintenanceCostItem> $items current contributions
   * @param array<string,MaintenanceCostItem> $originals captured sources keyed by kind and source
   *
   * @return list<MaintenanceCostItem> enriched current contributions
   */
  public function resolve(string $organizationId, string $interventionId, array $items, array $originals): array
  {
    $context = $this->work->economicContext($organizationId, $interventionId);
    if (null !== $context && ($context->organizationId !== $organizationId || $context->id !== $interventionId)) {
      throw MaintenanceCostException::conflict('Allocation identity is outside the authorized cost source.');
    }
    $tasks = [];
    foreach ($context->workItems ?? [] as $task) {
      $tasks[$task->id] = $task;
    }
    $capturedById = [];
    foreach ($originals as $captured) {
      $capturedById[$captured->id] = $captured;
    }
    $latePublication = null === $context?->publicationId ? null : 'publication:' . $context->publicationId;
    $capturedSources = [];
    $equipmentIds = [];
    foreach ($items as $item) {
      $original = $originals[$item->kind . ':' . $item->sourceId] ?? (null === $item->correctionOf ? null : ($capturedById[$item->correctionOf] ?? null));
      if (null !== $original) {
        $capturedSources[$item->id] = true;
      }
      $task = null === $item->workItemId ? null : ($tasks[$item->workItemId] ?? null);
      $id = $item->equipmentId ?? $task?->equipmentId;
      $useCapturedTask = null !== $task && (null === $id || $task->equipmentId === $id) && 'available' === $context?->snapshotState;
      $canUseLiveIdentity = 'live' === $context?->snapshotState || (null === $original && null !== $latePublication && $item->correctionOf === $latePublication);
      if (null !== $id && null === $original?->allocation && !$useCapturedTask && $canUseLiveIdentity) {
        $equipmentIds[$id] = true;
      }
    }
    $identities = [] === $equipmentIds ? [] : $this->equipment->snapshots($organizationId, array_keys($equipmentIds));
    $resolved = [];
    foreach ($items as $item) {
      $original = $originals[$item->kind . ':' . $item->sourceId] ?? (null === $item->correctionOf ? null : ($capturedById[$item->correctionOf] ?? null));
      $allocation = $original?->allocation;
      $task = null === $item->workItemId ? null : ($tasks[$item->workItemId] ?? null);
      $equipmentId = $item->equipmentId ?? $task?->equipmentId;
      if (null === $allocation) {
        $identity = null === $equipmentId ? null : ($identities[$equipmentId] ?? null);
        $useCapturedTask = null !== $task && (null === $equipmentId || $task->equipmentId === $equipmentId) && 'available' === $context?->snapshotState;
        $capturedIdentity = $useCapturedTask ? $task->equipmentIdentity : null;
        $historicSource = 'live' !== $context?->snapshotState && (null !== $original || null === $latePublication || $item->correctionOf !== $latePublication);
        // Only facts anchored to this publication may acquire a later live identity.
        $equipmentIdentity = $capturedIdentity ?? ($useCapturedTask || $historicSource ? null : $identity);
        $allocation = [
          'identityState' => null !== $capturedIdentity ? 'captured' : (null !== $equipmentIdentity || (null !== $task && 'live' === $context?->snapshotState) ? 'live' : 'incomplete'),
          'equipment' => null === $equipmentId ? null : ['id' => $equipmentId, 'name' => $equipmentIdentity?->name, 'assetReference' => $equipmentIdentity?->assetReference],
          'site' => $useCapturedTask ? $task->site : $equipmentIdentity?->site,
          'customer' => $useCapturedTask ? $task->customer : $equipmentIdentity?->customer,
        ];
        if (null === $equipmentId && null !== $task) {
          $allocation['site'] = $task->site;
          $allocation['customer'] = $task->customer;
        }
      }
      $resolved[] = new MaintenanceCostItem($item->id, $item->kind, $item->workItemId, $item->sourceId, $item->sourceRevision, $item->amount, $item->currency, $item->description, $item->occurredAt, $item->correctionOf, $item->hourlyAmount, $item->rateId, $equipmentId, $allocation);
    }

    return $this->inheritCurrentCorrections($resolved, $capturedSources);
  }

  /**
   * Method inheritCurrentCorrections
   *
   * Corrections of later facts retain the source allocation without changing frozen
   * contributions. Cyclic correction references retain each item's own identity.
   *
   * @access private
   *
   * @param list<MaintenanceCostItem> $items current contributions with resolved identities
   * @param array<string,true> $capturedSources sources already anchored to a frozen contribution
   *
   * @return list<MaintenanceCostItem> current contributions with source correction identities
   */
  private function inheritCurrentCorrections(array $items, array $capturedSources): array
  {
    $byId = [];
    foreach ($items as $item) {
      $byId[$item->id] = $item;
    }
    $sources = [];
    $resolved = [];
    foreach ($items as $item) {
      $path = [];
      $source = $item;
      while (true) {
        if (array_key_exists($source->id, $sources)) {
          $source = $sources[$source->id];

          break;
        }
        if (isset($path[$source->id])) {
          $source = null;

          break;
        }
        $path[$source->id] = true;
        if (isset($capturedSources[$source->id]) || null === $source->correctionOf || !isset($byId[$source->correctionOf])) {
          break;
        }
        $source = $byId[$source->correctionOf];
      }
      foreach (array_keys($path) as $id) {
        $sources[$id] = $source;
      }
      $resolved[] = null === $source || $source->id === $item->id ? $item : new MaintenanceCostItem($item->id, $item->kind, $item->workItemId, $item->sourceId, $item->sourceRevision, $item->amount, $item->currency, $item->description, $item->occurredAt, $item->correctionOf, $item->hourlyAmount, $item->rateId, $source->equipmentId, $source->allocation);
    }

    return $resolved;
  }
}
