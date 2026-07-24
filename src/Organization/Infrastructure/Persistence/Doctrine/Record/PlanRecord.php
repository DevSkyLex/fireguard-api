<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record PlanRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'plans')]
#[ORM\Index(name: 'idx_plan_is_default', columns: ['is_default'])]
#[ORM\Index(name: 'idx_plan_is_active_sort', columns: ['is_active', 'sort_order'])]
#[ORM\UniqueConstraint(name: 'uniq_plan_key', columns: ['plan_key'])]
class PlanRecord
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
   * Property key.
   *
   * Stable machine identifier of the plan.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'plan_key', type: 'string', length: 60)]
  public string $key;

  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 120)]
  public string $name;

  /**
   * Property description.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'description', type: 'text', nullable: true)]
  public ?string $description = null;

  /**
   * Property limits.
   *
   * The per-resource quantity caps persisted as a JSON object keyed by resource.
   * Typed as mixed values because the JSON payload is untrusted on read; the
   * mapper drops malformed entries.
   *
   * @var array<string, mixed>
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'limits', type: 'json')]
  public array $limits = [];

  /**
   * Property isActive.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'is_active', type: 'boolean')]
  public bool $isActive = true;

  /**
   * Property isDefault.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'is_default', type: 'boolean')]
  public bool $isDefault = false;

  /**
   * Property sortOrder.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'sort_order', type: 'integer')]
  public int $sortOrder = 0;

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
