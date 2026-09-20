<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record InterventionTimeEntryVersionRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'intervention_time_entry_versions')]

class InterventionTimeEntryVersionRecord
{
  /**
   * Property entry.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\ManyToOne(targetEntity: InterventionTimeEntryRecord::class)]
  #[ORM\JoinColumn(name: 'entry_id', nullable: false, onDelete: 'RESTRICT')]
  public ?InterventionTimeEntryRecord $entry = null;

  /**
   * Property revision.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(type: 'integer')]
  public int $revision;

  /**
   * Property workedOn.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 10)]
  public string $workedOn;

  /**
   * Property minutes.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'integer')]
  public int $minutes;

  /**
   * Property note.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'text', nullable: true)]
  public ?string $note = null;

  /**
   * Property cancelled.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'boolean')]
  public bool $cancelled = false;

  /**
   * Property actorId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 36)]
  public string $actorId;

  /**
   * Property recordedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'datetime_immutable')]
  public DateTimeImmutable $recordedAt;
}
