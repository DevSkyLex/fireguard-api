<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\ListFacilities;

use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListFacilitiesResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListFacilitiesResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<GetFacilityResult> $facilities the facilities list
   */
  public function __construct(
    public array $facilities,
  ) {
  }
  // #endregion
}
