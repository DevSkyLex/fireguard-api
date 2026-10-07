<?php

declare(strict_types=1);

namespace Procurement\Infrastructure\Persistence\Doctrine\Record;

use Doctrine\ORM\Mapping as ORM;

/** Main-database record retaining procurement evidence without historical deletion. */
#[ORM\Entity]
#[ORM\Table(name: 'procurement_operations')]
class ProcurementOperationRecord
{
  #[ORM\Id]
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  #[ORM\Id]
  #[ORM\Column(name: 'client_operation_id', type: 'string', length: 36)]
  public string $clientOperationId;

  #[ORM\Column(name: 'kind', type: 'string', length: 32)]
  public string $kind;

  #[ORM\Column(name: 'fingerprint', type: 'string', length: 64)]
  public string $fingerprint;

  #[ORM\Column(name: 'receipt_id', type: 'string', length: 36)]
  public string $receiptId;

  /**
   * @var array<array-key,mixed>
   */
  #[ORM\Column(name: 'declaration', type: 'json', options: ['jsonb' => true])]
  public array $declaration;
}
