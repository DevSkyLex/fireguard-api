<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Inspection\GetCanonicalInspection;

use Inspection\Application\Contract\Inspection\CanonicalInspectionView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetCanonicalInspectionResult.
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
final readonly class GetCanonicalInspectionResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public ?CanonicalInspectionView $view = null,
  ) {
  }
  // #endregion
}
