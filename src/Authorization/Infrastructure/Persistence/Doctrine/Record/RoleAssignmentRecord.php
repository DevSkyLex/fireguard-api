<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record RoleAssignmentRecord.
 *
 * Doctrine entity for role assignment persistence.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'role_assignments')]
#[ORM\Index(name: 'idx_assignments_subject', columns: ['subject_type', 'subject_id'])]
#[ORM\Index(name: 'idx_assignments_role', columns: ['role_id'])]
#[ORM\UniqueConstraint(name: 'unique_assignment', columns: ['role_id', 'subject_type', 'subject_id'])]
class RoleAssignmentRecord
{
  // #region Properties
  /**
   * Property id.
   *
   * The assignment ID.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property roleId.
   *
   * The role ID.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 36)]
  public string $roleId;

  /**
   * Property subjectType.
   *
   * The subject type (user).
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 20)]
  public string $subjectType;

  /**
   * Property subjectId.
   *
   * The subject ID.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 36)]
  public string $subjectId;

  /**
   * Property tenantId.
   *
   * The tenant ID for multi-tenant support.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 36, nullable: true)]
  public ?string $tenantId = null;

  /**
   * Property assignedAt.
   *
   * When the role was assigned.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'datetime_immutable')]
  public DateTimeImmutable $assignedAt;

  /**
   * Property expiresAt.
   *
   * When the assignment expires.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $expiresAt = null;

  /**
   * Property role.
   *
   * The role entity.
   *
   * @since 1.0.0
   */
  #[ORM\ManyToOne(targetEntity: RoleRecord::class)]
  #[ORM\JoinColumn(name: 'role_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
  public ?RoleRecord $role = null;
  // #endregion
}
