<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\MetadataField\ListMetadataFields;

/**
 * UseCase MetadataFieldItem.
 *
 * One row of {@see ListMetadataFieldsResult}.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MetadataFieldItem
{
  // #region Constructor
  /**
   * @param list<string> $options
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $key,
    public string $label,
    public string $fieldType,
    public bool $required,
    public array $options,
    public ?string $facilityType,
    public ?string $unit,
  ) {
  }
  // #endregion
}
