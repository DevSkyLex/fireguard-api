<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\MetadataField\DeleteMetadataField;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase DeleteMetadataFieldResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteMetadataFieldResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Returns the identifier of the metadata-field definition processed by deletion.
   *
   * @access public
   *
   * @param string $id identifier of the metadata field processed
   *
   * @return void
   */
  public function __construct(
    public string $id,
  ) {
  }
  // #endregion
}
