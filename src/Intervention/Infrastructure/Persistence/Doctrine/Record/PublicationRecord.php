<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Persistence\Doctrine\Record;

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
#[ORM\Table(name: 'intervention_publications')]
#[ORM\UniqueConstraint(name: 'uniq_publication_intervention_revision', columns: ['intervention_id', 'intervention_revision'])]
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
   * Property intervention.
   *
   * @since 1.0.0
   */
  #[ORM\ManyToOne(targetEntity: InterventionRecord::class, inversedBy: 'publications')]
  #[ORM\JoinColumn(name: 'intervention_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?InterventionRecord $intervention = null;

  /**
   * Property interventionRevision.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'intervention_revision', type: 'integer')]
  public int $interventionRevision;

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
