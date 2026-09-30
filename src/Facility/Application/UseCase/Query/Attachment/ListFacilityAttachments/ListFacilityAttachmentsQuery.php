<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Attachment\ListFacilityAttachments;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListFacilityAttachmentsQuery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListFacilityAttachmentsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the organization-scoped facility and optional attachment kind to list.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to authorize the lookup
   * @param string $facilityId facility whose attachments are listed
   * @param ?string $kind optional attachment classification filter
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $facilityId,
    public ?string $kind = null,
  ) {
  }
  // #endregion
}
