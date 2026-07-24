<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Attachment\ListFacilityAttachments;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListFacilityAttachmentsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListFacilityAttachmentsQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $facilityId,
  ) {
  }
  // #endregion
}
