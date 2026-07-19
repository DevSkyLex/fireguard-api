<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record TeamMemberRecord.
 *
 * Join record between a {@see TeamRecord} and an
 * {@see OrganizationMemberRecord}. `role` is a free-form membership label
 * (e.g. "lead"), explicitly NOT an RBAC role.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'team_members')]
#[ORM\Index(name: 'idx_team_members_member', columns: ['member_id'])]
class TeamMemberRecord
{
  // #region Properties
  /**
   * Property team.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\ManyToOne(targetEntity: TeamRecord::class, inversedBy: 'members')]
  #[ORM\JoinColumn(name: 'team_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?TeamRecord $team = null;

  /**
   * Property member.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\ManyToOne(targetEntity: OrganizationMemberRecord::class)]
  #[ORM\JoinColumn(name: 'member_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?OrganizationMemberRecord $member = null;

  /**
   * Property role.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 50, nullable: true)]
  public ?string $role = null;

  /**
   * Property addedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'added_at', type: 'datetime_immutable')]
  public DateTimeImmutable $addedAt;
  // #endregion
}
