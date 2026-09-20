<?php

declare(strict_types=1);

namespace Workload\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record CapacityExceptionRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'workload_capacity_exceptions')]
#[ORM\Index(name: 'idx_capacity_exception_member_date', columns: ['organization_id', 'member_id', 'starts_on', 'ends_on'])]
class CapacityExceptionRecord
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
   * Property memberId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 36)]
  public string $memberId;

  /**
   * Property startsOn.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 10)]
  public string $startsOn;

  /**
   * Property endsOn.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 10)]
  public string $endsOn;

  /**
   * Property minutes.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'integer')]
  public int $minutes;

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

  /**
   * Property cancelledAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $cancelledAt = null;

  /**
   * Property cancelledBy.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 36, nullable: true)]
  public ?string $cancelledBy = null;
}
