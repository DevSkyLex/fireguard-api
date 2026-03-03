<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Checklist\ListChecklists;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListChecklistsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListChecklistsQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public ?string $status = null,
  ) {
  }
  // #endregion
}
