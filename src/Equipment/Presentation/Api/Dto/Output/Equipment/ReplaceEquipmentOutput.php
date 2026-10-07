<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Dto\Output\Equipment;

use Equipment\Presentation\Api\Serialization\EquipmentSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Class ReplaceEquipmentOutput
 *
 * Durable replacement identities shared by first execution and replay.
 *
 * @category DTO
 */
final readonly class ReplaceEquipmentOutput
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $predecessorEquipmentId the historical equipment
   * @param string $successorEquipmentId the replacement equipment
   * @param string $clientOperationId the caller operation identity
   * @param bool $replayed whether this request returned the stored receipt
   *
   * @return void
   */
  public function __construct(
    #[Groups([EquipmentSerializationGroup::READ])]
    public string $predecessorEquipmentId,
    #[Groups([EquipmentSerializationGroup::READ])]
    public string $successorEquipmentId,
    #[Groups([EquipmentSerializationGroup::READ])]
    public string $clientOperationId,
    #[Groups([EquipmentSerializationGroup::READ])]
    public bool $replayed,
  ) {
  }
  // #endregion
}
