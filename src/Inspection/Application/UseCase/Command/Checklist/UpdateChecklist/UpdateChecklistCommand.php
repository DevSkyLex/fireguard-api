<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Checklist\UpdateChecklist;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase UpdateChecklistCommand.
 *
 * Partially updates a checklist: name, reference code, and/or the full item
 * list. Every field is paired with a `has*` flag so the handler can tell
 * "omitted" apart from "explicitly cleared" (PATCH semantics), mirroring
 * `EditInspectionCommand`.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateChecklistCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $checklistId the checklist identifier
   * @param ?string $name the new name, when provided
   * @param ?string $referenceCode the new reference code, when provided
   * @param ?list<array{label: string, description?: ?string, required?: bool, position?: int}> $items the new item list, when provided
   * @param bool $hasName whether the name field was provided
   * @param bool $hasReferenceCode whether the reference code field was provided
   * @param bool $hasItems whether the items field was provided
   */
  public function __construct(
    public string $organizationId,
    public string $checklistId,
    public ?string $name = null,
    public ?string $referenceCode = null,
    public ?array $items = null,
    public bool $hasName = false,
    public bool $hasReferenceCode = false,
    public bool $hasItems = false,
  ) {
  }
  // #endregion
}
