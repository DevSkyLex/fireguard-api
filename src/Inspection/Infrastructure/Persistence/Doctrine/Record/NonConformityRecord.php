<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class NonConformityRecord
 *
 * Persists non-conformity details and their inspection lifecycle timestamps.
 *
 * @category DoctrineRecord
 */
#[ORM\Entity]
#[ORM\Table(name: 'non_conformities')]
#[ORM\Index(name: 'idx_non_conformity_inspection', columns: ['inspection_id'])]
#[ORM\Index(name: 'idx_non_conformity_severity', columns: ['severity'])]
#[ORM\Index(name: 'idx_non_conformity_status', columns: ['status'])]
#[ORM\Index(name: 'idx_non_conformity_inspection_severity', columns: ['inspection_id', 'severity'])]
#[ORM\Index(name: 'idx_non_conformity_inspection_status', columns: ['inspection_id', 'status'])]
#[ORM\Index(name: 'idx_non_conformity_inspection_created_at', columns: ['inspection_id', 'created_at'])]
#[ORM\Index(name: 'idx_non_conformity_inspection_resolved_at', columns: ['inspection_id', 'resolved_at'])]
#[ORM\Index(name: 'idx_non_conformity_inspection_status_due_at', columns: ['inspection_id', 'status', 'due_at'])]
#[ORM\Index(name: 'idx_non_conformity_created_at_inspection', columns: ['created_at', 'inspection_id'])]
#[ORM\Index(name: 'idx_non_conformity_resolved_at_inspection_not_null', columns: ['resolved_at', 'inspection_id'], options: ['where' => '(resolved_at IS NOT NULL)'])]
class NonConformityRecord
{
  // #region Properties
  /**
   * Property id
   *
   * Non-conformity record identifier.
   *
   * @access public
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property inspection
   *
   * Inspection that owns this non-conformity.
   *
   * @access public
   */
  #[ORM\ManyToOne(targetEntity: InspectionRecord::class, inversedBy: 'nonConformities')]
  #[ORM\JoinColumn(name: 'inspection_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?InspectionRecord $inspection = null;

  /**
   * Property description
   *
   * Recorded description of the observed issue.
   *
   * @access public
   */
  #[ORM\Column(name: 'description', type: 'text')]
  public string $description;

  /**
   * Property severity
   *
   * Severity assigned to the issue.
   *
   * @access public
   */
  #[ORM\Column(name: 'severity', type: 'string', length: 16)]
  public string $severity;

  /**
   * Property status
   *
   * Current resolution status.
   *
   * @access public
   */
  #[ORM\Column(name: 'status', type: 'string', length: 16)]
  public string $status;

  /**
   * Property dueAt
   *
   * Optional deadline for resolution.
   *
   * @access public
   */
  #[ORM\Column(name: 'due_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $dueAt = null;

  /**
   * Property resolvedAt
   *
   * Optional time when the issue was resolved.
   *
   * @access public
   */
  #[ORM\Column(name: 'resolved_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $resolvedAt = null;

  /**
   * Property notes
   *
   * Optional supplementary notes.
   *
   * @access public
   */
  #[ORM\Column(name: 'notes', type: 'text', nullable: true)]
  public ?string $notes = null;

  /**
   * Anti-duplicate stamp for the SLA escalation sweep: set when the breach
   * was signalled, cleared when a resolved non-conformity is reopened
   * ({@see \Inspection\Infrastructure\Persistence\Doctrine\Repository\NonConformityRepository::save()}).
   */
  #[ORM\Column(name: 'sla_breach_notified_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $slaBreachNotifiedAt = null;

  /**
   * Property createdAt
   *
   * Record creation time.
   *
   * @access public
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property updatedAt
   *
   * Time of the latest record update.
   *
   * @access public
   */
  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;
  // #endregion
}
