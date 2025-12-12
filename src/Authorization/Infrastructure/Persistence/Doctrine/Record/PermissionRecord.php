<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record PermissionRecord
 *
 * Doctrine entity for permission persistence.
 *
 * @category Record
 * @package Authorization\Infrastructure\Persistence\Doctrine\Record
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'permissions')]
#[ORM\Index(name: 'idx_permissions_name', columns: ['name'])]
class PermissionRecord
{
  //#region Properties
  /**
   * Property id
   *
   * The permission ID.
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
   * The permission name (format: resource.action).
   *
   * @access public
   * @since 1.0.0
   *
   * @var string
   */
  #[ORM\Column(type: 'string', length: 100, unique: true)]
  public string $name;

  /**
   * Property description
   *
   * The permission description.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string
   */
  #[ORM\Column(type: 'text')]
  public string $description = '';

  /**
   * Property createdAt
   *
   * When the permission was created.
   *
   * @access public
   * @since 1.0.0
   *
   * @var DateTimeImmutable
   */
  #[ORM\Column(type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;
  //#endregion
}
