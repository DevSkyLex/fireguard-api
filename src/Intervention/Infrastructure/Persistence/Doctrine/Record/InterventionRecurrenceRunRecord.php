<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record InterventionRecurrenceRunRecord.
 *
 * One materialization attempt for a recurrence's occurrence. The unique
 * `(recurrence_id, occurrence_date)` constraint is the idempotence guard: a
 * Messenger retry or an overlapping sweep tick can never materialize the
 * same occurrence twice.
 *
 * Read the other way round — from a materialized intervention back to the
 * recurrence that produced it — this table is the only link there is, which
 * is why `intervention_id` carries its own index: an intervention detail read
 * resolves its origin through it.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'intervention_recurrence_runs')]
#[ORM\UniqueConstraint(name: 'uniq_intervention_recurrence_run_occurrence', columns: ['recurrence_id', 'occurrence_date'])]
#[ORM\Index(name: 'idx_intervention_recurrence_run_intervention', columns: ['intervention_id'])]
class InterventionRecurrenceRunRecord
{
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property recurrence.
   *
   * @since 1.0.0
   */
  #[ORM\ManyToOne(targetEntity: InterventionRecurrenceRecord::class)]
  #[ORM\JoinColumn(name: 'recurrence_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?InterventionRecurrenceRecord $recurrence = null;

  /**
   * Property occurrenceDate.
   *
   * The occurrence's calendar date, in the recurrence's own timezone.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'occurrence_date', type: 'date_immutable')]
  public DateTimeImmutable $occurrenceDate;

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 16)]
  public string $status = 'failed';

  /**
   * Property interventionId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'intervention_id', type: 'string', length: 36, nullable: true)]
  public ?string $interventionId = null;

  /**
   * Property error.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'text', nullable: true)]
  public ?string $error = null;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;
}
