<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\MetadataField\DeleteMetadataField;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase DeleteMetadataFieldResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteMetadataFieldResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public string $id,
  ) {
  }
  // #endregion
}
