<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Attachment\DeleteFacilityAttachment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteFacilityAttachmentCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteFacilityAttachmentCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies the facility attachment to delete within its organization.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to validate the facility
   * @param string $facilityId facility owning the attachment
   * @param string $attachmentId attachment to remove
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $facilityId,
    public string $attachmentId,
  ) {
  }
  // #endregion
}
