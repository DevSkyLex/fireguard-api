<?php

declare(strict_types=1);

namespace Import\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/** One receipt per committed CSV row; no uploaded row payload is retained. */
#[ORM\Entity]
#[ORM\Table(name: 'import_row_receipts')]
class ImportRowReceiptRecord
{
  #[ORM\Id]
  #[ORM\Column(name: 'import_job_id', type: 'string', length: 36)]
  public string $importJobId;

  #[ORM\Id]
  #[ORM\Column(name: 'row_number', type: 'integer')]
  public int $rowNumber;

  #[ORM\Column(type: 'string', length: 32)]
  public string $outcome;

  #[ORM\Column(name: 'resource_id', type: 'string', length: 36, nullable: true)]
  public ?string $resourceId = null;

  #[ORM\Column(name: 'confirmed_at', type: 'datetime_immutable')]
  public DateTimeImmutable $confirmedAt;
}
