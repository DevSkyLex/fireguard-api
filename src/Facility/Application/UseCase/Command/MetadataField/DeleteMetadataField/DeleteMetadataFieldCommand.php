<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\MetadataField\DeleteMetadataField;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteMetadataFieldCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteMetadataFieldCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $fieldId,
  ) {
  }
  // #endregion
}
