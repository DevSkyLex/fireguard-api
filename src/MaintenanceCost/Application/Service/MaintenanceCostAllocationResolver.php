<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Service;

use Intervention\Application\Contract\Publication\{InterventionEconomicContext, InterventionEquipmentSnapshot, InterventionPublishedWorkFact};
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
    $capturedSources = [];
    $originalSources = [];
    foreach ($items as $index => $item) {
      $original = $this->original($item, $originals, $capturedById);
      $originalSources[$index] = $original;
      if (null !== $original) {
        $capturedSources[$item->id] = true;
      }
    }
    $equipmentIds = $this->liveEquipmentIds($items, $originalSources, $tasks, $context);
    $identities = [] === $equipmentIds ? [] : $this->equipment->snapshots($organizationId, array_keys($equipmentIds));
    $resolved = [];
    foreach ($items as $index => $item) {
      $task = null === $item->workItemId ? null : ($tasks[$item->workItemId] ?? null);
      $equipmentId = $item->equipmentId ?? $task?->equipmentId;
      $original = $originalSources[$index];
      $allocation = $original->allocation ?? $this->allocation($item, $original, $task, $context, $identities, $equipmentId);
      $resolved[] = new MaintenanceCostItem($item->id, $item->kind, $item->workItemId, $item->sourceId, $item->sourceRevision, $item->amount, $item->currency, $item->description, $item->occurredAt, $item->correctionOf, $item->hourlyAmount, $item->rateId, $equipmentId, $allocation);
    }

    return $this->inheritCurrentCorrections($resolved, $capturedSources);
  }

  /**
   * Method original
   *
   * Source identity takes precedence over a correction reference when both point to captured facts.
   *
   * @access private
   *
   * @param MaintenanceCostItem $item current contribution
   * @param array<string,MaintenanceCostItem> $originals captured facts keyed by kind and source
   * @param array<string,MaintenanceCostItem> $capturedById captured facts keyed by contribution identity
   *
   * @return ?MaintenanceCostItem captured source when retained
   */
  private function original(MaintenanceCostItem $item, array $originals, array $capturedById): ?MaintenanceCostItem
  {
    return $originals[$item->kind . ':' . $item->sourceId] ?? (null === $item->correctionOf ? null : ($capturedById[$item->correctionOf] ?? null));
  }

  /**
   * Method liveEquipmentIds
   *
   * Looks up current labels only for sources allowed to acquire them.
   *
   * @access private
   *
   * @param list<MaintenanceCostItem> $items current contributions
   * @param array<int,?MaintenanceCostItem> $originalSources captured source at each current contribution index
   * @param array<string,InterventionPublishedWorkFact> $tasks owned task identities
   * @param ?InterventionEconomicContext $context publication provenance
   *
   * @return array<string,true> equipment identities eligible for a live lookup
   */
  private function liveEquipmentIds(array $items, array $originalSources, array $tasks, ?InterventionEconomicContext $context): array
  {
    $equipmentIds = [];
    foreach ($items as $index => $item) {
      $original = $originalSources[$index];
      $task = null === $item->workItemId ? null : ($tasks[$item->workItemId] ?? null);
      $id = $item->equipmentId ?? $task?->equipmentId;
      if (null !== $id && null === $original?->allocation && !$this->useCapturedTask($context, $task, $id) && $this->canUseLiveIdentity($context, $item, $original)) {
        $equipmentIds[$id] = true;
      }
    }

    return $equipmentIds;
  }

  /**
   * Method useCapturedTask
   *
   * An available dossier can provide only the identity of its own matching task target.
   *
   * @access private
   *
   * @param ?InterventionEconomicContext $context publication provenance
   * @param ?InterventionPublishedWorkFact $task owned task
   * @param ?string $equipmentId contribution target
   *
   * @return bool whether the captured task is the identity authority
   */
  private function useCapturedTask(?InterventionEconomicContext $context, ?InterventionPublishedWorkFact $task, ?string $equipmentId): bool
  {
    return null !== $task && (null === $equipmentId || $task->equipmentId === $equipmentId) && 'available' === $context?->snapshotState;
  }

  /**
   * Method canUseLiveIdentity
   *
   * Only an unpublished source or a proven new fact anchored to this publication may acquire later labels.
   *
   * @access private
   *
   * @param ?InterventionEconomicContext $context publication provenance
   * @param MaintenanceCostItem $item current contribution
   * @param ?MaintenanceCostItem $original captured source
   *
   * @return bool whether live park identities are permitted
   */
  private function canUseLiveIdentity(?InterventionEconomicContext $context, MaintenanceCostItem $item, ?MaintenanceCostItem $original): bool
  {
    return 'live' === $context?->snapshotState || (null === $original && null !== $context?->publicationId && $item->correctionOf === 'publication:' . $context->publicationId);
  }

  /**
   * Method allocation
   *
   * Retains incomplete historical identities rather than substituting the current park.
   *
   * @access private
   *
   * @param MaintenanceCostItem $item current contribution
   * @param ?MaintenanceCostItem $original captured source
   * @param ?InterventionPublishedWorkFact $task owned task identity
   * @param ?InterventionEconomicContext $context publication provenance
   * @param array<string,InterventionEquipmentSnapshot> $identities permitted current equipment identities
   * @param ?string $equipmentId contribution target
   *
   * @return array{identityState:string,equipment:?array{id:string,name:?string,assetReference:?string},site:?array{id:string,name:string},customer:?array{id:string,name:string}} private allocation identity
   */
  private function allocation(MaintenanceCostItem $item, ?MaintenanceCostItem $original, ?InterventionPublishedWorkFact $task, ?InterventionEconomicContext $context, array $identities, ?string $equipmentId): array
  {
    $useCapturedTask = $this->useCapturedTask($context, $task, $equipmentId);
    $capturedIdentity = $useCapturedTask ? $task?->equipmentIdentity : null;
    $equipmentIdentity = $capturedIdentity;
    if (null === $equipmentIdentity && !$useCapturedTask && $this->canUseLiveIdentity($context, $item, $original)) {
      $equipmentIdentity = null === $equipmentId ? null : ($identities[$equipmentId] ?? null);
    }
    $state = 'incomplete';
    if (null !== $capturedIdentity) {
      $state = 'captured';
    } elseif (null !== $equipmentIdentity || (null !== $task && 'live' === $context?->snapshotState)) {
      $state = 'live';
    }
    $allocation = [
      'identityState' => $state,
      'equipment' => null === $equipmentId ? null : ['id' => $equipmentId, 'name' => $equipmentIdentity?->name, 'assetReference' => $equipmentIdentity?->assetReference],
      'site' => $useCapturedTask ? $task?->site : $equipmentIdentity?->site,
      'customer' => $useCapturedTask ? $task?->customer : $equipmentIdentity?->customer,
    ];
    if (null === $equipmentId && null !== $task) {
      $allocation['site'] = $task->site;
      $allocation['customer'] = $task->customer;
    }

    return $allocation;
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
      $source = $this->correctionSource($item, $byId, $capturedSources, $sources);
      $resolved[] = null === $source || $source->id === $item->id ? $item : new MaintenanceCostItem($item->id, $item->kind, $item->workItemId, $item->sourceId, $item->sourceRevision, $item->amount, $item->currency, $item->description, $item->occurredAt, $item->correctionOf, $item->hourlyAmount, $item->rateId, $source->equipmentId, $source->allocation);
    }

    return $resolved;
  }

  /**
   * Method correctionSource
   *
   * Memoizes each correction chain's source; cycles retain each item's own identity.
   *
   * @access private
   *
   * @param MaintenanceCostItem $item contribution whose allocation must be inherited
   * @param array<string,MaintenanceCostItem> $byId current source lookup
   * @param array<string,true> $capturedSources frozen source anchors
   * @param array<string,?MaintenanceCostItem> $sources memoized chain results
   *
   * @return ?MaintenanceCostItem terminal source or null for a cyclic chain
   */
  private function correctionSource(MaintenanceCostItem $item, array $byId, array $capturedSources, array &$sources): ?MaintenanceCostItem
  {
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

    return $source;
  }
}
