<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Checklist\ListChecklists;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListChecklistResult.
 *
 * Lightweight per-row projection for the checklist list endpoint (L1.10b).
 * Unlike `GetChecklist\GetChecklistResult` (used by the single-GET,
 * create/update/archive use cases), this result never carries the full
 * `items` array — only the scalar `itemCount` a list view actually needs,
 * so the list query does not have to hydrate every checklist's items.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListChecklistResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Projects checklist identity, version, lifecycle, item count, and editability for a collection row.
   *
   * @access public
   *
   * @param string $checklistId identifier of the checklist
   * @param string $organizationId organization owning the checklist
   * @param string $name display name of the checklist
   * @param string $version checklist version label
   * @param string $status lifecycle status of the checklist
   * @param int $itemCount number of checklist items
   * @param DateTimeImmutable $createdAt time the checklist was created
   * @param DateTimeImmutable $updatedAt time the checklist was last updated
   * @param ?string $referenceCode optional stable reference code
   * @param ?string $previousChecklistId identifier of the prior checklist version, when linked
   * @param bool $itemsEditable whether checklist items can currently be edited
   *
   * @return void
   */
  public function __construct(
    public string $checklistId,
    public string $organizationId,
    public string $name,
    public string $version,
    public string $status,
    public int $itemCount,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public ?string $referenceCode = null,
    public ?string $previousChecklistId = null,
    public bool $itemsEditable = false,
  ) {
  }
  // #endregion
}
