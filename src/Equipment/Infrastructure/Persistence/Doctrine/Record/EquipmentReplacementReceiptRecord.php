<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class EquipmentReplacementReceiptRecord
 *
 * Main-database replay journal; historical equipment identifiers are never cascaded away.
 *
 * @category Record
 */
#[ORM\Entity]
#[ORM\Table(name: 'equipment_replacement_receipts')]
class EquipmentReplacementReceiptRecord
{
  // #region Properties
  /**
   * Property organizationId
   */
  #[ORM\Id]
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property clientOperationId
   */
  #[ORM\Id]
  #[ORM\Column(name: 'client_operation_id', type: 'string', length: 36)]
  public string $clientOperationId;

  /**
   * Property predecessorEquipmentId
   */
  #[ORM\Column(name: 'predecessor_equipment_id', type: 'string', length: 36)]
  public string $predecessorEquipmentId;

  /**
   * Property successorEquipmentId
   */
  #[ORM\Column(name: 'successor_equipment_id', type: 'string', length: 36)]
  public string $successorEquipmentId;

  /**
   * Property payloadHash
   */
  #[ORM\Column(name: 'payload_hash', type: 'string', length: 64)]
  public string $payloadHash;

  /**
   * Property createdAt
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;
  // #endregion
}
