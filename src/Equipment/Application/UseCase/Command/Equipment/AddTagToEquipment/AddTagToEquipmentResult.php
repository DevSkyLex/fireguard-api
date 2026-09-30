<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\AddTagToEquipment;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase AddTagToEquipmentResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddTagToEquipmentResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Returns the tag identity and organization associated with the equipment after the command completes.
   *
   * @access public
   *
   * @param string $tagId identifier of the tag linked to equipment
   * @param string $tagName normalized tag name returned by the tag capability
   * @param string $organizationId organization owning the tag
   *
   * @return void
   */
  public function __construct(
    public string $tagId,
    public string $tagName,
    public string $organizationId,
  ) {
  }
  // #endregion
}
