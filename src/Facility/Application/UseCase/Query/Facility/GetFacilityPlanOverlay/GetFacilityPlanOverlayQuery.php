<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityPlanOverlay;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetFacilityPlanOverlayQuery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityPlanOverlayQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies the organization-scoped facility and optional plan attachment requested for the overlay.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to authorize the read
   * @param string $facilityId facility whose plan overlay is requested
   * @param ?string $attachmentId specific attachment to use, or null for the facility primary plan
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $facilityId,
    public ?string $attachmentId = null,
  ) {
  }
  // #endregion
}
