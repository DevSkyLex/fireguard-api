<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Inspection\ReadCanonicalInspection;

use Inspection\Application\Contract\Inspection\CanonicalInspectionReadView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ReadCanonicalInspectionResult.
 *
 * `view` is null when nothing matches — the caller decides the status.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ReadCanonicalInspectionResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the canonical inspection read view when a record is available.
   *
   * @access public
   *
   * @param ?CanonicalInspectionReadView $view canonical inspection view, or null when no record is available
   *
   * @return void
   */
  public function __construct(
    public ?CanonicalInspectionReadView $view = null,
  ) {
  }
  // #endregion
}
