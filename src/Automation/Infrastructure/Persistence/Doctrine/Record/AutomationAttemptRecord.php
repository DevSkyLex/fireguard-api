<?php

declare(strict_types=1);

namespace Automation\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/** Record AutomationAttemptRecord. Immutable identity and retained outcome for each execution. */
#[ORM\Entity]
#[ORM\Table(name: 'automation_attempts')]
#[ORM\Index(name: 'idx_automation_attempt_run', columns: ['run_id'])]
#[ORM\UniqueConstraint(name: 'uniq_automation_attempt_number', columns: ['run_id', 'attempt_number'])]
class AutomationAttemptRecord
{
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  #[ORM\ManyToOne(targetEntity: AutomationRunRecord::class)]
  #[ORM\JoinColumn(name: 'run_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public AutomationRunRecord $run;

  #[ORM\Column(name: 'attempt_number', type: 'integer')]
  public int $attemptNumber;

  #[ORM\Column(type: 'string', length: 16)]
  public string $status;

  #[ORM\Column(name: 'requested_by', type: 'string', length: 36, nullable: true)]
  public ?string $requestedBy = null;

  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  #[ORM\Column(name: 'finished_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $finishedAt = null;
}
