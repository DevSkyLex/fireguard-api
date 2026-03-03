<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Inspection\GetInspection;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetInspectionQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetInspectionQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
  ) {
  }
  // #endregion
}
