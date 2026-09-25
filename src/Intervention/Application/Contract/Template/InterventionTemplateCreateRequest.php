<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Template;

/**
 * A complete validated template, including its ordered work items.
 */
final readonly class InterventionTemplateCreateRequest
{
  /**
   * @param list<string> $labelIds
   * @param list<array{action: string, target: ?string, resultResource: ?string, required: bool, defaultAssigneeId: ?string, estimatedMinutes?: ?int}> $items
   */
  public function __construct(
    public string $organizationId,
    public InterventionTemplateAttributes $attributes,
    public InterventionTemplateDefaults $defaults,
    public array $labelIds,
    public array $items,
  ) {
  }
}
