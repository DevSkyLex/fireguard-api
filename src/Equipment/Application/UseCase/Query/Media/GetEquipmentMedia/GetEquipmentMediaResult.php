<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Media\GetEquipmentMedia;

use Equipment\Application\Contract\Media\EquipmentMediaView;
use Shared\Application\Message\ResultMessage;

/**
 * Carries authorized media metadata.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetEquipmentMediaResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor. @since 1.0.0
   */
  public function __construct(public EquipmentMediaView $view)
  {
  }
  // #endregion
}
