<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Checklist\GetChecklist;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetChecklistResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetChecklistResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<ChecklistItemResult> $items the checklist items
   */
  public function __construct(
    public string $checklistId,
    public string $organizationId,
    public string $name,
    public string $version,
    public string $status,
    public array $items,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public ?string $referenceCode = null,
  ) {
  }
  // #endregion
}
