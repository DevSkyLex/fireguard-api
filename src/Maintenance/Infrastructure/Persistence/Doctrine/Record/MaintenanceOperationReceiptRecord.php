<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/** Main-database persistence record for multi-operation maintenance. */
#[ORM\Entity]
#[ORM\Table(name: 'maintenance_operation_receipts')]

class MaintenanceOperationReceiptRecord
{
  #[ORM\Id]
  #[ORM\Column(name: 'id', type: 'string', length: 36)]
  public string $id;

  #[ORM\Column(name: 'occurrence_id', type: 'string', length: 36)]
  public string $occurrenceId;

  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  #[ORM\Column(name: 'outcome', type: 'string', length: 16)]
  public string $outcome;

  #[ORM\Column(name: 'performed_at', type: 'datetime_immutable')]
  public DateTimeImmutable $performedAt;
}
