<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Report\ResolveInspectionReportAccess;

use Shared\Application\Message\QueryMessage;

/** Resolves report scope and plan entitlement before export filter validation.
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResolveInspectionReportAccessQuery implements QueryMessage
{
  public function __construct(public string $userId, public string $organizationId)
  {
  }
}
