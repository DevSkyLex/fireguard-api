<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\ReplaceEquipment;

use Equipment\Application\Contract\Replacement\EquipmentReplacementSuccessor;
use Shared\Application\Message\CommandMessage;

/**
 * Class ReplaceEquipmentCommand
 *
 * Replaces one asset using exactly one existing successor or new asset description.
 *
 * @category UseCase
 */
final readonly class ReplaceEquipmentCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $organizationId the owner
   * @param string $equipmentId the predecessor
   * @param string $clientOperationId the stable client operation identity
   * @param ?string $successorEquipmentId the unused stock asset
   * @param ?EquipmentReplacementSuccessor $successor the new asset description
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
    public string $clientOperationId,
    public ?string $successorEquipmentId = null,
    public ?EquipmentReplacementSuccessor $successor = null,
  ) {
  }
  // #endregion
}
