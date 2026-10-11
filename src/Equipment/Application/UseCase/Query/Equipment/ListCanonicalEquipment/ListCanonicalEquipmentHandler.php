<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\ListCanonicalEquipment;

use Equipment\Application\Contract\Equipment\{CanonicalEquipmentCollectionItem, CanonicalEquipmentCollectionPage};
use Equipment\Application\Port\Outbound\{CanonicalEquipmentCollectionPort, MaintenanceDueStatusPort};
use Equipment\Domain\Exception\{EquipmentAccessDeniedException, EquipmentMediaNotFoundException, EquipmentRequestInvalidException};
use Intervention\Application\Port\Inbound\InterventionResourcesPort;
use Maintenance\Application\Port\Inbound\MaintenanceOperationsDuePort;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

use function array_map;
use function max;
use function min;

/**
 * Authorizes a collection and resolves maintenance in bounded batches.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListCanonicalEquipmentHandler implements QueryHandler
{
  // #region Constructor
  /**
   * @since 1.0.0
   */
  public function __construct(
    private CanonicalEquipmentCollectionPort $equipment,
    private OrganizationAuthorizationPort $authorization,
    private InterventionResourcesPort $interventions,
    private ?MaintenanceOperationsDuePort $operationsDue = null,
    private ?MaintenanceDueStatusPort $legacyDueStatus = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * @since 1.0.0
   */
  public function __invoke(ListCanonicalEquipmentQuery $query): ListCanonicalEquipmentResult
  {
    $organizationId = $query->organizationId ?? (null === $query->interventionId ? null : $this->interventions->interventionContext($query->interventionId)?->organizationId);
    if (null === $organizationId) {
      throw new EquipmentRequestInvalidException('The organization or intervention filter is required.');
    }
    $decision = $this->authorization->resolveAccess($query->userId, $organizationId, 'organization.equipment.read');
    if ($decision->isOutsideScope()) {
      throw new EquipmentMediaNotFoundException('Organization not found.');
    }
    if (!$decision->isGranted()) {
      throw new EquipmentAccessDeniedException('Missing organization.equipment.read permission.');
    }
    $page = $this->equipment->read(
      $organizationId,
      $query->recordStatus ?? (null === $query->interventionId ? 'published' : 'draft'),
      $query->interventionId,
      $query->facilityId,
      max(1, $query->page),
      max(1, min(100, $query->itemsPerPage)),
    );
    $ids = array_map(static fn (CanonicalEquipmentCollectionItem $item): string => $item->equipment->id, $page->items);
    $due = $this->operationsDue?->forEquipment($organizationId, $ids) ?? [];
    $legacy = null === $this->operationsDue ? ($this->legacyDueStatus?->dueStatusesForEquipment($organizationId, $ids) ?? []) : [];
    $items = [];
    foreach ($page->items as $item) {
      $items[] = new CanonicalEquipmentCollectionItem($item->equipment, $due[$item->equipment->id] ?? null, $legacy[$item->equipment->id] ?? 'unscheduled');
    }

    return new ListCanonicalEquipmentResult(new CanonicalEquipmentCollectionPage($items, $page->total, $page->page, $page->itemsPerPage));
  }
  // #endregion
}
