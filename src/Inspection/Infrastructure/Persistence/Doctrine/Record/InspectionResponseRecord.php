<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Record InspectionResponseRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'inspection_responses')]
#[ORM\Index(name: 'idx_response_inspection', columns: ['inspection_id'])]
#[ORM\Index(name: 'idx_response_mission_status', columns: ['mission_id', 'record_status'])]
class InspectionResponseRecord
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
   * Property organization.
   *
   * @since 1.0.0
   */
  #[ORM\ManyToOne(targetEntity: OrganizationRecord::class)]
  #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?OrganizationRecord $organization = null;

  /**
   * Property missionId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'mission_id', type: 'string', length: 36, nullable: true)]
  public ?string $missionId = null;

  /**
   * Property inspectionId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'inspection_id', type: 'string', length: 36)]
  public string $inspectionId;

  /**
   * Property clientId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'client_id', type: 'string', length: 36, nullable: true, unique: true)]
  public ?string $clientId = null;

  /**
   * Property recordStatus.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'record_status', type: 'string', length: 16)]
  public string $recordStatus = 'published';

  /**
   * Property revision.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'revision', type: 'integer')]
  public int $revision = 1;

  /**
   * Property itemKey.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'item_key', type: 'string', length: 160)]
  public string $itemKey;

  /**
   * Property value.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'value', type: 'json', nullable: true)]
  public mixed $value = null;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;
}
