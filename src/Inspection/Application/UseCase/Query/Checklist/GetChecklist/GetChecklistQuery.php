<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Checklist\GetChecklist;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetChecklistQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetChecklistQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $checklistId,
  ) {
  }
  // #endregion
}
