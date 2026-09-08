<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Sweep\EscalateNonConformitySlaBreaches;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase EscalateNonConformitySlaBreachesResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EscalateNonConformitySlaBreachesResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param int $escalatedCount the number of SLA breach escalations sent
   */
  public function __construct(
    public int $escalatedCount,
  ) {
  }
  // #endregion
}
