<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\ListCanonicalEquipment;

use Shared\Application\Message\QueryMessage;

/**
 * Requests a canonical page from organization or intervention scope.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListCanonicalEquipmentQuery implements QueryMessage
{
  // #region Constructor
  /**
   * @since 1.0.0
   */
  public function __construct(public string $userId, public ?string $organizationId = null, public ?string $interventionId = null, public ?string $recordStatus = null, public ?string $facilityId = null, public int $page = 1, public int $itemsPerPage = 50)
  {
  }
  // #endregion
}
