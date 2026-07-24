<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Record InterventionLabelRecord.
 *
 * Organization-scoped intervention label: a small, reusable `{name, color}`
 * tag interventions can be assigned to, for lightweight categorization on
 * top of the workflow status.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'intervention_labels')]
#[ORM\UniqueConstraint(name: 'uniq_intervention_label_org_name', columns: ['organization_id', 'name'])]
class InterventionLabelRecord
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
   * Property name.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 50)]
  public string $name;

  /**
   * Property color.
   *
   * A `#rrggbb` hex color string.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 7)]
  public string $color;

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
