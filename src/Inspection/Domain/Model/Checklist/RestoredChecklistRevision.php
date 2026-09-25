<?php

declare(strict_types=1);

namespace Inspection\Domain\Model\Checklist;

use Inspection\Domain\ValueObject\{ChecklistId, ChecklistStatus};

/** Persisted revision state, kept separate from checklist identity and timestamps. */
final readonly class RestoredChecklistRevision
{
  /**
   * @param list<ChecklistItem> $items
   */
  public function __construct(
    public string $version,
    public ChecklistStatus $status,
    public array $items,
    public ?string $referenceCode = null,
    public ?ChecklistId $previousChecklistId = null,
  ) {
  }
}
