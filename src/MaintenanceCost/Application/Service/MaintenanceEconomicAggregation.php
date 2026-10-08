<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Service;

use MaintenanceCost\Application\Contract\Reporting\{MaintenanceEconomicAmount, MaintenanceEconomicRow};
use MaintenanceCost\Domain\Exception\MaintenanceCostException;

use function bcadd;
use function bccomp;
use function usort;

/**
 * Class MaintenanceEconomicAggregation
 *
 * Keeps source, selected and excluded amounts together so every contribution
 * reconciles while entering only one allocation bucket.
 *
 * @category Service
 */
final class MaintenanceEconomicAggregation
{
  // #region Properties
  /**
   * Property unallocated
   *
   * Keeps global facts and unresolved target scope in one independent row.
   */
  public readonly MaintenanceEconomicBucket $unallocated;

  /**
   * Property buckets
   *
   * @var array<string,MaintenanceEconomicBucket>
   */
  private array $buckets = [];

  /**
   * Property selected
   *
   * @var array<string,MaintenanceEconomicAccumulator>
   */
  private array $selected;

  /**
   * Property source
   *
   * @var array<string,MaintenanceEconomicAccumulator>
   */
  private array $source;

  /**
   * Property excluded
   *
   * @var array<string,MaintenanceEconomicAccumulator>
   */
  private array $excluded;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Starts one report's independent accumulation state.
   *
   * @access public
   *
   * @return void
   */
  public function __construct()
  {
    $this->unallocated = new MaintenanceEconomicBucket(null, null);
    $this->selected = ['current' => new MaintenanceEconomicAccumulator(), 'frozen' => new MaintenanceEconomicAccumulator(), 'planned' => new MaintenanceEconomicAccumulator(), 'budget' => new MaintenanceEconomicAccumulator()];
    $this->source = ['current' => new MaintenanceEconomicAccumulator(), 'frozen' => new MaintenanceEconomicAccumulator(), 'planned' => new MaintenanceEconomicAccumulator()];
    $this->excluded = ['current' => new MaintenanceEconomicAccumulator(), 'frozen' => new MaintenanceEconomicAccumulator(), 'planned' => new MaintenanceEconomicAccumulator()];
  }
  // #endregion

  // #region Methods
  /**
   * Method retain
   *
   * Records each source once, keeping known different targets separate and unresolved scope unallocated.
   *
   * @access public
   *
   * @param string $kind current, frozen or planned contribution
   * @param ?string $amount exact valuation or unknown
   * @param array{identityState:string,equipment:?array{id:string,name:?string,assetReference:?string},site:?array{id:string,name:string},customer:?array{id:string,name:string}} $allocation retained source identity
   * @param string $interventionId source dossier identity
   * @param ?array{id:string,name:?string} $identity selected grouping identity
   * @param ?bool $matches true matching target, false known different target, null unresolved scope
   *
   * @return void
   */
  public function retain(string $kind, ?string $amount, array $allocation, string $interventionId, ?array $identity, ?bool $matches): void
  {
    $this->source[$kind]->add($amount);
    $hasAllocation = null !== $allocation['equipment'] || null !== $allocation['site'] || null !== $allocation['customer'];
    if ($hasAllocation && false === $matches) {
      $this->excluded[$kind]->add($amount);

      return;
    }
    if (null === $matches) {
      $identity = null;
    }
    $this->selected[$kind]->add($amount);
    if (null === $identity) {
      $bucket = $this->unallocated;
    } else {
      $bucket = $this->buckets[$identity['id']] ??= new MaintenanceEconomicBucket($identity['id'], $identity['name']);
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
   * Method budget
   *
   * The budget stays separate from resource estimates and belongs to no target bucket.
   *
   * @access public
   *
   * @param ?string $amount exact declared budget or unknown
   * @param string $interventionId source dossier identity
   * @param bool $published whether the source is captured
   *
   * @return void
   */
  public function budget(?string $amount, string $interventionId, bool $published): void
  {
    $this->unallocated->budget->add($amount);
    $this->selected['budget']->add($amount);
    $this->unallocated->source($interventionId, $published ? 'captured' : 'live', false, null);
  }

  /**
   * Method amount
   *
   * Supplies a full-scope selected total independently of row pagination.
   *
   * @access public
   *
   * @param string $kind selected contribution category
   *
   * @return MaintenanceEconomicAmount exact subtotal and completeness
   */
  public function amount(string $kind): MaintenanceEconomicAmount
  {
    return $this->selected[$kind]->amount();
  }

  /**
   * Method rows
   *
   * Sorts complete allocation rows before the caller paginates them.
   *
   * @access public
   *
   * @return list<MaintenanceEconomicRow> all allocated destinations
   */
  public function rows(): array
  {
    $rows = [];
    foreach ($this->buckets as $bucket) {
      $rows[] = $bucket->row();
    }
    usort($rows, static fn ($a, $b): int => ($a->name ?? $a->id ?? '') <=> ($b->name ?? $b->id ?? ''));

    return $rows;
  }

  /**
   * Method reconciliation
   *
   * Verifies exact amounts and both known and unknown contribution counts across selection boundaries.
   *
   * @access public
   *
   * @return array{sourceCurrent:MaintenanceEconomicAmount,excludedCurrent:MaintenanceEconomicAmount,sourceFrozen:MaintenanceEconomicAmount,excludedFrozen:MaintenanceEconomicAmount,sourcePlanned:MaintenanceEconomicAmount,excludedPlanned:MaintenanceEconomicAmount,reconciled:bool} complete reconciliation evidence
   */
  public function reconciliation(): array
  {
    $reconciled = true;
    foreach (['current', 'frozen', 'planned'] as $kind) {
      $includedAmount = $this->selected[$kind]->amount();
      $excludedAmount = $this->excluded[$kind]->amount();
      $sourceAmount = $this->source[$kind]->amount();
      $sum = bcadd($includedAmount->knownTotal, $excludedAmount->knownTotal, 6);
      $reconciled = $reconciled && 0 === bccomp($sum, $sourceAmount->knownTotal, 6)
        && $includedAmount->contributionCount + $excludedAmount->contributionCount === $sourceAmount->contributionCount
        && $includedAmount->unknownCount + $excludedAmount->unknownCount === $sourceAmount->unknownCount;
    }

    return ['sourceCurrent' => $this->source['current']->amount(), 'excludedCurrent' => $this->excluded['current']->amount(), 'sourceFrozen' => $this->source['frozen']->amount(), 'excludedFrozen' => $this->excluded['frozen']->amount(), 'sourcePlanned' => $this->source['planned']->amount(), 'excludedPlanned' => $this->excluded['planned']->amount(), 'reconciled' => $reconciled];
  }
  // #endregion
}
