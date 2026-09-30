<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Attachment\SetPrimaryFacilityAttachment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase SetPrimaryFacilityAttachmentCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetPrimaryFacilityAttachmentCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies the facility attachment to designate as its primary plan within the organization.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to validate the facility
   * @param string $facilityId facility whose primary plan is changed
   * @param string $attachmentId attachment to select as the primary plan
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
