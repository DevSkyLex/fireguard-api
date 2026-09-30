<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\GetEquipmentAttachmentContent;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetEquipmentAttachmentContentQuery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetEquipmentAttachmentContentQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies an attachment and its owning equipment within an organization for content retrieval.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to validate the equipment
   * @param string $equipmentId equipment owning the attachment
   * @param string $attachmentId attachment whose content is requested
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
    public string $attachmentId,
  ) {
  }
  // #endregion
}
