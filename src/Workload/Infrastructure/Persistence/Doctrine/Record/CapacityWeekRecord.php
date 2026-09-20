<?php

declare(strict_types=1);

namespace Workload\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record CapacityWeekRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'workload_capacity_weeks')]
#[ORM\UniqueConstraint(name: 'uniq_capacity_scope_date', columns: ['organization_id', 'scope_id', 'effective_on'])]
class CapacityWeekRecord
{
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property scopeId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 36)]
  public string $scopeId;

  /**
   * Property effectiveOn.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 10)]
  public string $effectiveOn;

  /**
   * Property minutes.
   *
   * @since 1.0.0
   *
   * @var list<int>
   */
  #[ORM\Column(type: 'json')]
  public array $minutes = [];

  /**
   * Property createdBy.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 36)]
  public string $createdBy;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;
}
