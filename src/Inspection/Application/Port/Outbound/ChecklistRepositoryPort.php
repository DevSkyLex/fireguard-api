<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

use Inspection\Domain\Model\Checklist\Checklist;
use Inspection\Domain\ValueObject\{ChecklistId, ChecklistOrganizationId};

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
  ): array;
  // #endregion
}
