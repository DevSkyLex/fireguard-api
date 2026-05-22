<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\Common\Collections\{ArrayCollection, Collection};
use Doctrine\ORM\Mapping as ORM;

/**
 * Record OrganizationMemberRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'organization_members')]
#[ORM\Index(name: 'idx_organization_member_organization', columns: ['organization_id'])]
#[ORM\Index(name: 'idx_organization_member_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_organization_member_organization_active', columns: ['organization_id', 'is_active'])]
#[ORM\Index(name: 'idx_organization_member_user_active', columns: ['user_id', 'is_active'])]
#[ORM\Index(name: 'idx_organization_member_organization_joined_at', columns: ['organization_id', 'joined_at'])]
#[ORM\UniqueConstraint(name: 'uniq_organization_user_member', columns: ['organization_id', 'user_id'])]
class OrganizationMemberRecord
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
  #[ORM\ManyToOne(targetEntity: OrganizationRecord::class, inversedBy: 'members')]
  #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?OrganizationRecord $organization = null;

  /**
   * Property userId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'user_id', type: 'string', length: 36)]
  public string $userId;

  /**
   * Property isActive.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'is_active', type: 'boolean')]
  public bool $isActive = true;

  /**
   * Property joinedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'joined_at', type: 'datetime_immutable')]
  public DateTimeImmutable $joinedAt;

  /**
   * Property roleAssignments.
   *
   * @var Collection<int, OrganizationMemberRoleRecord>
   */
  #[ORM\OneToMany(mappedBy: 'member', targetEntity: OrganizationMemberRoleRecord::class, cascade: ['remove'])]
  public Collection $roleAssignments;

  /**
   * Constructor.
   */
  public function __construct()
  {
    $this->roleAssignments = new ArrayCollection();
  }
  // #endregion
}
