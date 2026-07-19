<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

use Inspection\Domain\Model\Checklist\Checklist;
use Inspection\Domain\ValueObject\{ChecklistId, ChecklistOrganizationId};
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

/**
 * Port ChecklistRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ChecklistRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists a checklist aggregate.
   *
   * @since 1.0.0
   *
   * @param Checklist $checklist the checklist aggregate
   */
  public function save(Checklist $checklist): void;

  /**
   * Method findById.
   *
   * Finds a checklist by identifier.
   *
   * @since 1.0.0
   *
   * @param ChecklistId $id the checklist identifier
   *
   * @return ?Checklist the checklist aggregate when found
   */
  public function findById(ChecklistId $id): ?Checklist;

  /**
   * Method findByOrganizationId.
   *
   * Lists checklists for an organization.
   *
   * @since 1.0.0
   *
   * @param ChecklistOrganizationId $organizationId the organization identifier
   * @param ?string $status optional status filter
   *
   * @return list<Checklist> the checklist list
   */
  public function findByOrganizationId(
    ChecklistOrganizationId $organizationId,
    ?string $status = null,
    ?string $search = null,
    Sorting $sorting = new Sorting('createdAt', SortDirection::DESC),
    int $limit = 20,
    int $offset = 0,
  ): array;

  public function countByOrganizationId(
    ChecklistOrganizationId $organizationId,
    ?string $status = null,
    ?string $search = null,
  ): int;

  /**
   * Method countItemsGroupedByChecklistId.
   *
   * Counts items for a bounded set of checklists in a single grouped query
   * (`GROUP BY checklist_id` over `checklist_items`, joined to `checklists`
   * and filtered by organization), avoiding one hydration pass per checklist
   * (L1.10b). Checklists with zero items are simply absent from the map; the
   * caller must default missing keys to `0`. A checklist ID belonging to a
   * different organization never contributes to the result.
   *
   * @since 1.0.0
   *
   * @param ChecklistOrganizationId $organizationId the organization identifier
   * @param list<string> $checklistIds the checklist identifiers to count items for
   *
   * @return array<string, int> map of checklistId => item count
   */
  public function countItemsGroupedByChecklistId(
    ChecklistOrganizationId $organizationId,
    array $checklistIds,
  ): array;
  // #endregion
}
