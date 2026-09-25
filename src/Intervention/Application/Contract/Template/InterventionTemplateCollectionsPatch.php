<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Template;

/**
 * Collection replacements; null clears a present list to an empty list.
 */
final readonly class InterventionTemplateCollectionsPatch
{
  /**
   * @param ?list<string> $labelIds
   * @param ?list<array{action: string, target: ?string, resultResource: ?string, required: bool, defaultAssigneeId: ?string, estimatedMinutes?: ?int}> $items
   */
  public function __construct(
    public ?array $labelIds,
    public ?array $items,
    public bool $hasLabelIds,
    public bool $hasItems,
  ) {
  }
}
