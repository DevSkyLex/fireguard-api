<?php

declare(strict_types=1);

namespace Tenant\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Record TenantRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'tenants')]
class TenantRecord
{
  // #region Properties
  /**
   * Property id.
   *
   * The tenant ID.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(type: UuidType::NAME, unique: true)]
  public Uuid $id;

  /**
   * Property name.
   *
   * The tenant name.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 100, unique: true)]
  public string $name;

  /**
   * Property settings.
   *
   * The tenant settings as JSON.
   *
   * @since 1.0.0
   *
   * @var array<string, mixed>
   */
  #[ORM\Column(type: 'json')]
  public array $settings = [];

  /**
   * Property isActive.
   *
   * Whether the tenant is active.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'is_active', type: 'boolean')]
  public bool $isActive = true;

  /**
   * Property createdAt.
   *
   * The creation timestamp.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;
  // #endregion
}
