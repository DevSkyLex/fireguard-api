<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\GetCanonicalEquipment;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetCanonicalEquipmentQuery.
 *
 * Deliberately unscoped by organization: the canonical routes carry no
 * organization segment, so the organization to check against is the one on
 * the row itself. The permission gate that follows the read is what makes it
 * safe, and it answers 404 for a row outside the caller's scope.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCanonicalEquipmentQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $equipmentId,
  ) {
  }
  // #endregion
}
