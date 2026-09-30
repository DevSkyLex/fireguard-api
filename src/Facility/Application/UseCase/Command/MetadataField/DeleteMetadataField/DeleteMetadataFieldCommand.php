<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\MetadataField\DeleteMetadataField;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteMetadataFieldCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteMetadataFieldCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies a metadata field within its organization for deletion.
   *
   * @access public
   *
   * @param string $organizationId organization owning the metadata field
   * @param string $fieldId metadata-field definition to remove
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $fieldId,
  ) {
  }
  // #endregion
}
