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
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListChecklistResult implements ResultMessage
{
  // #region Constructor
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
  ) {
  }
  // #endregion
}
