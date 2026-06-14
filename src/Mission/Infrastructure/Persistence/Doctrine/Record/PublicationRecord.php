<?php

declare(strict_types=1);

namespace Mission\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record PublicationRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'mission_publications')]
#[ORM\UniqueConstraint(name: 'uniq_publication_mission_revision', columns: ['mission_id', 'mission_revision'])]
class PublicationRecord
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
   * Property mission.
   *
   * @since 1.0.0
   */
  #[ORM\ManyToOne(targetEntity: MissionRecord::class, inversedBy: 'publications')]
  #[ORM\JoinColumn(name: 'mission_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?MissionRecord $mission = null;

  /**
   * Property missionRevision.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'mission_revision', type: 'integer')]
  public int $missionRevision;

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 16)]
  public string $status = 'pending';

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

  /**
   * Property completedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'completed_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $completedAt = null;
}
