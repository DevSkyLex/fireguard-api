<?php

declare(strict_types=1);

namespace Inventory\Infrastructure\Persistence\Doctrine\Record;

use Doctrine\ORM\Mapping as ORM;

/** Main database record. @category Record */
#[ORM\Entity]
#[ORM\Table(name:'inventory_operation_receipts')]
class InventoryOperationRecord
{
  #[ORM\Id]
  #[ORM\Column(name:'organization_id', type:'string', length:36)]
  public string $organizationId;

  #[ORM\Id]
  #[ORM\Column(name:'client_operation_id', type:'string', length:36)]
  public string $clientOperationId;

  #[ORM\Column(name:'payload_hash', type:'string', length:64)]
  public string $payloadHash;

  /**
   * @var array<string,mixed>
   */
  #[ORM\Column(name:'response', type:'json')]
  public array $response;
}
