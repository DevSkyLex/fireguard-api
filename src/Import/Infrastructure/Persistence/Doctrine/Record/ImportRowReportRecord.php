<?php

declare(strict_types=1);

namespace Import\Infrastructure\Persistence\Doctrine\Record;

use Doctrine\ORM\Mapping as ORM;

/**
 * Record ImportRowReportRecord.
 *
 * A single confirmed CSV row report, retained independently of job counters.
 *
 * @category Record
 */
#[ORM\Entity]
#[ORM\Table(name: 'import_row_reports')]
class ImportRowReportRecord
{
  #[ORM\Id]
  #[ORM\Column(name: 'import_job_id', type: 'string', length: 36)]
  public string $importJobId;

  #[ORM\Id]
  #[ORM\Column(name: 'row_number', type: 'integer')]
  public int $rowNumber;

  #[ORM\Column(type: 'string', length: 32)]
  public string $code;

  #[ORM\Column(type: 'text')]
  public string $message;

  #[ORM\Column(name: 'column_name', type: 'string', length: 255, nullable: true)]
  public ?string $columnName = null;
}
