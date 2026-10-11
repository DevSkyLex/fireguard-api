<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\ListCanonicalEquipment;

use Equipment\Application\Contract\Equipment\CanonicalEquipmentCollectionPage;
use Shared\Application\Message\ResultMessage;

/**
 * Returns the authorized canonical collection.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListCanonicalEquipmentResult implements ResultMessage
{
  // #region Constructor
  /**
   * @since 1.0.0
   */
  public function __construct(public CanonicalEquipmentCollectionPage $page)
  {
  }
  // #endregion
}
