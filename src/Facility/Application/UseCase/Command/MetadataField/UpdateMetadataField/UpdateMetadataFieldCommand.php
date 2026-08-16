<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\MetadataField\UpdateMetadataField;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase UpdateMetadataFieldCommand.
 *
 * A partial update: each `has*` flag reports whether the field was present
 * in the PATCH payload at all, matching the Facility module's own
 * PATCH-semantics convention (see UpdateFacilityCommand).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateMetadataFieldCommand implements CommandMessage
{
  // #region Constructor
  /**
   * @param ?list<mixed> $options
   */
  public function __construct(
    public string $organizationId,
    public string $fieldId,
    public ?string $label = null,
    public bool $hasLabel = false,
    public ?string $fieldType = null,
    public bool $hasFieldType = false,
    public ?array $options = null,
    public bool $hasOptions = false,
    public ?bool $required = null,
    public bool $hasRequired = false,
    public ?string $facilityType = null,
    public bool $hasFacilityType = false,
    public ?string $unit = null,
    public bool $hasUnit = false,
  ) {
  }
  // #endregion
}
