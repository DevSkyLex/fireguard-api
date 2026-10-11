<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Report\ResolveInspectionReportAccess;

use Shared\Application\Message\ResultMessage;

/** Authorized report plan snapshot.
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResolveInspectionReportAccessResult implements ResultMessage
{
  public function __construct(public string $planKey)
  {
  }
}
