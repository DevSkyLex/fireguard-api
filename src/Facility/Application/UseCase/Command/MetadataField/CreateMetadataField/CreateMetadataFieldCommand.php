<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\MetadataField\CreateMetadataField;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase CreateMetadataFieldCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateMetadataFieldCommand implements CommandMessage
{
  // #region Constructor
  /**
   * @param list<mixed> $options
   */
  public function __construct(
    public string $organizationId,
    public string $key,
    public string $label,
    public string $fieldType,
    public bool $required = false,
    public array $options = [],
    public ?string $facilityType = null,
    public ?string $unit = null,
  ) {
  }
  // #endregion
}
