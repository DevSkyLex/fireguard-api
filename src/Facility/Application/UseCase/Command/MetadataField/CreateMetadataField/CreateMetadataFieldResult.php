<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\MetadataField\CreateMetadataField;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase CreateMetadataFieldResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateMetadataFieldResult implements ResultMessage
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
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
