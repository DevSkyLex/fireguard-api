<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\ListEquipmentAttachments;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListEquipmentAttachmentsQuery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListEquipmentAttachmentsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies the organization-scoped equipment whose attachments are requested.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to validate the equipment
   * @param string $equipmentId equipment whose attachments are listed
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
  ) {
  }
  // #endregion
}
