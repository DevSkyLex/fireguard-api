<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\Common\Collections\{ArrayCollection, Collection};
use Doctrine\ORM\Mapping as ORM;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Class ChecklistRecord
 *
 * Stores checklist identity, ownership, lifecycle metadata and its item collection.
 *
 * @category DoctrineRecord
 */
#[ORM\Entity]
#[ORM\Table(name: 'checklists')]
#[ORM\Index(name: 'idx_checklist_organization', columns: ['organization_id'])]
#[ORM\Index(name: 'idx_checklist_status', columns: ['status'])]
#[ORM\Index(name: 'idx_checklist_previous', columns: ['previous_checklist_id'])]
#[ORM\Index(name: 'idx_checklist_organization_status', columns: ['organization_id', 'status'])]
#[ORM\UniqueConstraint(name: 'uniq_checklist_organization_reference_code', columns: ['organization_id', 'reference_code'])]
class ChecklistRecord
{
  // #region Properties
  /**
   * Property id
   *
   * Identifies the checklist record.
   *
   * @access public
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property organization
   *
   * Organization that owns this checklist.
   *
   * @access public
   */
  #[ORM\ManyToOne(targetEntity: OrganizationRecord::class, inversedBy: 'checklists')]
  #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?OrganizationRecord $organization = null;

  /**
   * Property referenceCode
   *
   * Human-facing reference code (`CHK-EXT-Q`), unique per organization when
   * set. Nullable: every checklist created before this column existed has
   * none, and the code is optional metadata rather than an identity.
   */
  #[ORM\Column(name: 'reference_code', type: 'string', length: 40, nullable: true)]
  public ?string $referenceCode = null;

  /**
   * Property previousChecklist
   *
   * Earlier checklist from which this checklist may derive.
   *
   * @access public
   */
  #[ORM\ManyToOne(targetEntity: self::class)]
  #[ORM\JoinColumn(name: 'previous_checklist_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
  public ?self $previousChecklist = null;

  /**
   * Property name
   *
   * Display name of the checklist.
   *
   * @access public
   */
  #[ORM\Column(name: 'name', type: 'string', length: 255)]
  public string $name;

  /**
   * Property version
   *
   * Checklist version label.
   *
   * @access public
   */
  #[ORM\Column(name: 'version', type: 'string', length: 50)]
  public string $version;

  /**
   * Property status
   *
   * Current checklist lifecycle status.
   *
   * @access public
   */
  #[ORM\Column(name: 'status', type: 'string', length: 16)]
  public string $status;

  /**
   * Property createdAt
   *
   * Time when the checklist record was created.
   *
   * @access public
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property updatedAt
   *
   * Time when the checklist record was last updated.
   *
   * @access public
   */
  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;

  /**
   * Property items
   *
   * Checklist items associated with this record.
   *
   * @var Collection<int, ChecklistItemRecord>
   *
   * @access public
   */
  #[ORM\OneToMany(mappedBy: 'checklist', targetEntity: ChecklistItemRecord::class, cascade: ['remove'])]
  public Collection $items;

  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Initializes the checklist item collection.
   *
   * @access public
   *
   * @return void
   */
  public function __construct()
  {
    $this->items = new ArrayCollection();
  }
  // #endregion
}
