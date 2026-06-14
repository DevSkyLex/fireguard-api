<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\Common\Collections\{ArrayCollection, Collection};
use Doctrine\ORM\Mapping as ORM;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Record InterventionRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'interventions')]
#[ORM\Index(name: 'idx_intervention_organization_status', columns: ['organization_id', 'status'])]
class InterventionRecord
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
   * Property type.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 32)]
  public string $type = 'site_setup';

  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 160)]
  public string $name;

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 24)]
  public string $status = 'draft';

  /**
   * Property referencePackId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'reference_pack_id', type: 'string', length: 80)]
  public string $referencePackId = 'fr-erp-ert-v1';

  /**
   * Property siteId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'site_id', type: 'string', length: 36, nullable: true)]
  public ?string $siteId = null;

  /**
   * Property responsibleId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'responsible_id', type: 'string', length: 36, nullable: true)]
  public ?string $responsibleId = null;

  /**
   * Property participants.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[ORM\Column(type: 'json')]
  public array $participants = [];

  /**
   * Property priority.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 16)]
  public string $priority = 'normal';

  /**
   * Property plannedStartAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'planned_start_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $plannedStartAt = null;

  /**
   * Property dueAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'due_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $dueAt = null;

  /**
   * Property reviewNote.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'review_note', type: 'text', nullable: true)]
  public ?string $reviewNote = null;

  /**
   * Property revision.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'integer')]
  public int $revision = 1;

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
   * Property publications.
   *
   * @since 1.0.0
   *
   * @var Collection<int, PublicationRecord>
   */
  #[ORM\OneToMany(mappedBy: 'intervention', targetEntity: PublicationRecord::class, cascade: ['remove'])]
  public Collection $publications;

  /**
   * Property workItems.
   *
   * @since 1.0.0
   *
   * @var Collection<int, InterventionWorkItemRecord>
   */
  #[ORM\OneToMany(mappedBy: 'intervention', targetEntity: InterventionWorkItemRecord::class, cascade: ['remove'])]
  public Collection $workItems;

  /**
   * Property changes.
   *
   * @since 1.0.0
   *
   * @var Collection<int, InterventionChangeRecord>
   */
  #[ORM\OneToMany(mappedBy: 'intervention', targetEntity: InterventionChangeRecord::class, cascade: ['remove'])]
  public Collection $changes;

  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionRecord class.
   *
   * @since 1.0.0
   */
  public function __construct()
  {
    $this->publications = new ArrayCollection();
    $this->workItems = new ArrayCollection();
    $this->changes = new ArrayCollection();
  }
}
