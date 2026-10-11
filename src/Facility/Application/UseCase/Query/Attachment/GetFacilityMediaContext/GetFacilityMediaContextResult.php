<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext;

use Facility\Application\Contract\Attachment\FacilityAttachmentView;
use Shared\Application\Message\ResultMessage;

/**
 * Result GetFacilityMediaContextResult.
 *
 * Carries an authorized owner and optional attachment snapshot.
 *
 * @category Result
 */
final readonly class GetFacilityMediaContextResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct.
   */
  public function __construct(
    public string $organizationId,
    public string $facilityId,
    public ?FacilityAttachmentView $attachment = null,
  ) {
  }
  // #endregion
}
