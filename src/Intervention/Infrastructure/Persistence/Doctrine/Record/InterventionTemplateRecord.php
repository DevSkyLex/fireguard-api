<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\Common\Collections\{ArrayCollection, Collection};
use Doctrine\ORM\Mapping as ORM;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Record InterventionTemplateRecord.
 *
 * Organization-scoped intervention template: a reusable blueprint (type,
 * priority, defaults, planned items) instantiated into a real intervention
 * draft through the workflow gateway.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'intervention_templates')]
#[ORM\Index(name: 'idx_intervention_template_organization', columns: ['organization_id'])]
#[ORM\UniqueConstraint(name: 'uniq_intervention_template_org_name', columns: ['organization_id', 'name'])]
class InterventionTemplateRecord
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
  #[ORM\Column(type: 'string', length: 160)]
  public string $name;

  /**
   * Property description.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'text', nullable: true)]
  public ?string $description = null;

  /**
   * Property type.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'intervention_type', type: 'string', length: 32)]
  public string $type = 'site_setup';

  /**
   * Property priority.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'default_priority', type: 'string', length: 16)]
  public string $priority = 'normal';

  /**
   * Property defaultSiteId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'default_site_id', type: 'string', length: 36, nullable: true)]
  public ?string $defaultSiteId = null;

  /**
   * Property defaultResponsibleId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'default_responsible_id', type: 'string', length: 36, nullable: true)]
  public ?string $defaultResponsibleId = null;

  /**
   * Property duration.
   *
   * ISO-8601 duration string (e.g. `P14D`), used to derive `dueAt` from
   * `plannedStartAt` at instantiation.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 32, nullable: true)]
  public ?string $duration = null;

  /**
   * Property labelIds.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[ORM\Column(name: 'label_ids', type: 'json')]
  public array $labelIds = [];

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

  /**
   * Property items.
   *
   * @since 1.0.0
   *
   * @var Collection<int, InterventionTemplateItemRecord>
   */
  #[ORM\OneToMany(mappedBy: 'template', targetEntity: InterventionTemplateItemRecord::class, cascade: ['remove'], orphanRemoval: true)]
  public Collection $items;

  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionTemplateRecord class.
   *
   * @since 1.0.0
   */
  public function __construct()
  {
    $this->items = new ArrayCollection();
  }
}
