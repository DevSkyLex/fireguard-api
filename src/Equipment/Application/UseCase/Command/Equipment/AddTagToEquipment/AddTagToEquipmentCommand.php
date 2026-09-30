<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\AddTagToEquipment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase AddTagToEquipmentCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddTagToEquipmentCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the organization-scoped equipment and tag name to associate with it.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to resolve the equipment and tag
   * @param string $equipmentId equipment receiving the tag
   * @param string $tagName tag name to normalize, find, or create
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
    public string $tagName,
  ) {
  }
  // #endregion
}
