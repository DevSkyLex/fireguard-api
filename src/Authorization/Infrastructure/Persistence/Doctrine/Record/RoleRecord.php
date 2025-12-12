<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record RoleRecord
 *
 * Doctrine entity for role persistence.
 *
 * @category Record
 * @package Authorization\Infrastructure\Persistence\Doctrine\Record
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'roles')]
#[ORM\Index(name: 'idx_roles_tenant', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_roles_name', columns: ['name'])]
class RoleRecord
{
  //#region Properties
  /**
   * Property id
   *
   * The role ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property name
   *
   * The role name.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string
   */
  #[ORM\Column(type: 'string', length: 50, unique: true)]
  public string $name;

  /**
   * Property description
   *
   * The role description.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string
   */
  #[ORM\Column(type: 'text')]
  public string $description = '';

  /**
   * Property isSystem
   *
   * Whether this is a system role (cannot be deleted).
   *
   * @access public
   * @since 1.0.0
   *
   * @var bool
   */
  #[ORM\Column(type: 'boolean')]
  public bool $isSystem = false;

  /**
   * Property tenantId
   *
   * The tenant ID for multi-tenant support.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string|null
   */
  #[ORM\Column(type: 'string', length: 36, nullable: true)]
  public ?string $tenantId = null;

  /**
   * Property createdAt
   *
   * When the role was created.
   *
   * @access public
   * @since 1.0.0
   *
   * @var DateTimeImmutable
   */
  #[ORM\Column(type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property updatedAt
   *
   * When the role was last updated.
   *
   * @access public
   * @since 1.0.0
   *
   * @var DateTimeImmutable|null
   */
  #[ORM\Column(type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $updatedAt = null;

  /**
   * Property permissions
   *
   * The permissions assigned to this role.
   *
   * @access public
   * @since 1.0.0
   *
   * @var Collection<int, PermissionRecord>
   */
  #[ORM\ManyToMany(targetEntity: PermissionRecord::class)]
  #[ORM\JoinTable(name: 'role_permissions')]
  #[ORM\JoinColumn(name: 'role_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
  #[ORM\InverseJoinColumn(name: 'permission_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
  public Collection $permissions;
  //#endregion

  //#region Constructor
  /**
   * Constructor
   */
  public function __construct()
  {
    $this->permissions = new ArrayCollection();
  }
  //#endregion
}
