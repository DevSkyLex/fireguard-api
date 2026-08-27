<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetCanonicalFacility;

use Facility\Application\Contract\Facility\CanonicalFacilityView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetCanonicalFacilityResult.
 *
 * `view` is null when nothing matches — the caller decides the status,
 * because "absent" and "outside your scope" must answer alike here.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCanonicalFacilityResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public ?CanonicalFacilityView $view = null,
  ) {
  }
  // #endregion
}
