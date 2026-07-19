<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\Common\Collections\{ArrayCollection, Collection};
use Doctrine\ORM\Mapping as ORM;

/**
 * Record TeamRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'teams')]
#[ORM\Index(name: 'idx_team_org', columns: ['organization_id'])]
#[ORM\UniqueConstraint(name: 'uniq_team_org_name', columns: ['organization_id', 'name'])]
class TeamRecord
{
  // #region Properties
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
  #[ORM\Column(type: 'string', length: 80)]
  public string $name;

  /**
   * Property description.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'text', options: ['default' => ''])]
  public string $description = '';

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
   * Property members.
   *
   * @var Collection<int, TeamMemberRecord>
   */
  #[ORM\OneToMany(mappedBy: 'team', targetEntity: TeamMemberRecord::class, cascade: ['remove'])]
  public Collection $members;

  /**
   * Constructor.
   */
  public function __construct()
  {
    $this->members = new ArrayCollection();
  }
  // #endregion
}
