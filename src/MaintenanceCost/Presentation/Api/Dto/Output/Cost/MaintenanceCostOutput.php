<?php

declare(strict_types=1);

namespace MaintenanceCost\Presentation\Api\Dto\Output\Cost;

use MaintenanceCost\Application\Contract\Cost\{MaintenanceCostItem, MaintenanceCostView};
use Symfony\Component\Serializer\Attribute\Groups;

use function array_map;

/** Class MaintenanceCostOutput. Dedicated private contract with no embedding in ordinary intervention reads. @category Output */
final class MaintenanceCostOutput
{
  #[Groups(['maintenance_cost:read'])]
  public string $id = '';

  #[Groups(['maintenance_cost:read'])]
  public string $interventionId = '';

  #[Groups(['maintenance_cost:read'])]
  public string $organizationId = '';

  #[Groups(['maintenance_cost:read'])]
  public string $currency = '';

  #[Groups(['maintenance_cost:read'])]
  public ?string $plannedBudget = null;

  #[Groups(['maintenance_cost:read'])]
  public ?int $estimatedMinutes = null;

  #[Groups(['maintenance_cost:read'])]
  public int $planningRevision = 0;

  #[Groups(['maintenance_cost:read'])]
  public bool $planningEditable = false;

  /**
   * @var list<array{workItemId:?string,kind:string,description:string,quantity:?string,unitCost:?string,estimatedMinutes:?int,amount:?string}>
   */
  #[Groups(['maintenance_cost:read'])]
  public array $resources = [];

  /**
   * @var array<string,mixed>
   */
  #[Groups(['maintenance_cost:read'])]
  public array $current = [];

  /**
   * @var array<string,mixed>|null
   */
  #[Groups(['maintenance_cost:read'])]
  public ?array $frozen = null;

  public static function fromView(MaintenanceCostView $view): self
  {
    $output = new self();
    $output->id = $view->interventionId;
    $output->interventionId = $view->interventionId;
    $output->organizationId = $view->organizationId;
    $output->currency = $view->currency;
    $output->plannedBudget = $view->planning->plannedBudget;
    $output->estimatedMinutes = $view->planning->estimatedMinutes;
    $output->planningRevision = $view->planning->revision;
    $output->planningEditable = $view->planningEditable;
    $output->resources = $view->planning->resources;
    $output->current = ['total' => $view->current->total, 'knownTotal' => $view->current->knownTotal, 'complete' => $view->current->complete, 'items' => array_map(static fn (MaintenanceCostItem $item): array => $item->toArray(), $view->current->items)];
    if (null !== $view->frozen) {
      $snapshot = $view->frozen;
      $output->frozen = ['version' => $snapshot->version, 'capturedAt' => $snapshot->capturedAt, 'publicationId' => $snapshot->publicationId, 'interventionRevision' => $snapshot->interventionRevision, 'currency' => $snapshot->currency, 'total' => $snapshot->totals->total, 'knownTotal' => $snapshot->totals->knownTotal, 'complete' => $snapshot->totals->complete, 'plannedBudget' => $snapshot->planning?->plannedBudget, 'estimatedMinutes' => $snapshot->planning?->estimatedMinutes, 'resources' => $snapshot->planning->resources ?? [], 'items' => array_map(static fn (MaintenanceCostItem $item): array => $item->toArray(), $snapshot->totals->items)];
    }

    return $output;
  }
}
