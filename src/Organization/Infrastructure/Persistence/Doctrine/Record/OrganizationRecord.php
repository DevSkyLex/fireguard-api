<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record OrganizationRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'organizations')]
#[ORM\Index(name: 'idx_organization_created_by_user', columns: ['created_by_user_id'])]
#[ORM\Index(name: 'idx_organization_owner_user', columns: ['owner_user_id'])]
#[ORM\Index(name: 'idx_organization_status', columns: ['status'])]
#[ORM\Index(name: 'idx_organization_status_name', columns: ['status', 'name'])]
#[ORM\UniqueConstraint(name: 'uniq_organization_slug', columns: ['slug'])]
class OrganizationRecord
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
   * Property name.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 120)]
  public string $name;

  /**
   * Property slug.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'slug', type: 'string', length: 120)]
  public string $slug;

  /**
   * Property ownerUserId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'owner_user_id', type: 'string', length: 36)]
  public string $ownerUserId;

  /**
   * Property createdByUserId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_by_user_id', type: 'string', length: 36)]
  public string $createdByUserId;

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'status', type: 'string', length: 20)]
  public string $status = 'active';

  /**
   * Property isActive.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'is_active', type: 'boolean')]
  public bool $isActive = true;

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
  // #endregion
}
