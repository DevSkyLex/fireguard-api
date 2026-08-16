<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityPlanOverlay;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetFacilityPlanOverlayQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityPlanOverlayQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $facilityId,
    public ?string $attachmentId = null,
  ) {
  }
  // #endregion
}
