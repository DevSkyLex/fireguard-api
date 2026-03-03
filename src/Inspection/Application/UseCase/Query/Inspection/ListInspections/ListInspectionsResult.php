<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Inspection\ListInspections;

use Inspection\Application\UseCase\Query\Inspection\GetInspection\GetInspectionResult;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListInspectionsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInspectionsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<GetInspectionResult> $inspections the inspection list
   */
  public function __construct(
    public array $inspections,
  ) {
  }
  // #endregion
}
