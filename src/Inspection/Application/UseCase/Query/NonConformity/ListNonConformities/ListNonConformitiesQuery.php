<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\NonConformity\ListNonConformities;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListNonConformitiesQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListNonConformitiesQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
    public ?string $severity = null,
    public ?string $status = null,
  ) {
  }
  // #endregion
}
