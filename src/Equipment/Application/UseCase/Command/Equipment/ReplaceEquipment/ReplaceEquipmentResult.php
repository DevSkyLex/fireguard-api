<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\ReplaceEquipment;

use Shared\Application\Message\ResultMessage;

/**
 * Class ReplaceEquipmentResult
 *
 * Original equipment identities returned for both first execution and replay.
 *
 * @category UseCase
 */
final readonly class ReplaceEquipmentResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $predecessorEquipmentId the historical equipment
   * @param string $successorEquipmentId the replacement equipment
   * @param string $clientOperationId the stable operation identity
   * @param bool $replayed whether an existing receipt supplied the result
   *
   * @return void
   */
  public function __construct(
    public string $predecessorEquipmentId,
    public string $successorEquipmentId,
    public string $clientOperationId,
    public bool $replayed,
  ) {
  }
  // #endregion
}
