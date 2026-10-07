<?php

declare(strict_types=1);

namespace ServiceRequest\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Main-database request with scalar retained references to owner modules.
 *
 * @category Record
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'service_requests')]
#[ORM\Index(name: 'idx_service_request_org_requested', columns: ['organization_id', 'requested_at'])]
#[ORM\Index(name: 'idx_service_request_org_status', columns: ['organization_id', 'status', 'requested_at'])]
#[ORM\Index(name: 'idx_service_request_org_equipment', columns: ['organization_id', 'equipment_id'])]
#[ORM\Index(name: 'idx_service_request_org_site', columns: ['organization_id', 'site_id'])]
class ServiceRequestRecord
{
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  #[ORM\Column(name: 'equipment_id', type: 'string', length: 36, nullable: true)]
  public ?string $equipmentId = null;

  #[ORM\Column(name: 'site_id', type: 'string', length: 36, nullable: true)]
  public ?string $siteId = null;

  /**
   * @var array<string,mixed> retained target, site and internal customer identity
   */
  #[ORM\Column(name: 'target_snapshot', type: 'json', options: ['jsonb' => true])]
  public array $targetSnapshot = [];

  #[ORM\Column(type: 'string', length: 160)]
  public string $title;

  #[ORM\Column(type: 'text')]
  public string $description;

  #[ORM\Column(type: 'string', length: 16, options: ['default' => 'normal'])]
  public string $priority = 'normal';

  #[ORM\Column(name: 'origin_inspection_id', type: 'string', length: 36, nullable: true)]
  public ?string $originInspectionId = null;

  #[ORM\Column(name: 'origin_non_conformity_id', type: 'string', length: 36, nullable: true)]
  public ?string $originNonConformityId = null;

  #[ORM\Column(type: 'string', length: 16, options: ['default' => 'requested'])]
  public string $status = 'requested';

  #[ORM\Column(type: 'integer', options: ['default' => 1])]
  public int $revision = 1;

  #[ORM\Column(name: 'requested_at', type: 'datetime_immutable')]
  public DateTimeImmutable $requestedAt;

  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;

  #[ORM\Column(name: 'qualified_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $qualifiedAt = null;

  #[ORM\Column(name: 'rejected_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $rejectedAt = null;

  #[ORM\Column(name: 'cancelled_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $cancelledAt = null;

  #[ORM\Column(name: 'converted_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $convertedAt = null;

  #[ORM\Column(name: 'decision_reason', type: 'text', nullable: true)]
  public ?string $decisionReason = null;

  #[ORM\Column(name: 'qualification_note', type: 'text', nullable: true)]
  public ?string $qualificationNote = null;

  #[ORM\Column(name: 'intervention_id', type: 'string', length: 36, nullable: true)]
  public ?string $interventionId = null;

  #[ORM\Column(name: 'task_id', type: 'string', length: 36, nullable: true)]
  public ?string $taskId = null;
}
