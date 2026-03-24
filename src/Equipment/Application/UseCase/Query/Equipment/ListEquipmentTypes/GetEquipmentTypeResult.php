<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\ListEquipmentTypes;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetEquipmentTypeResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetEquipmentTypeResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetEquipmentTypeResult class.
   *
   * @since 1.0.0
   *
   * @param string $value the equipment type value
   * @param string $label the human-readable label
   */
  public function __construct(
    public string $value,
    public string $label,
  ) {
  }
  // #endregion
}
