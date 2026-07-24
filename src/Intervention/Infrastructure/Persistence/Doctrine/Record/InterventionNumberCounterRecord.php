<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Persistence\Doctrine\Record;

use Doctrine\ORM\Mapping as ORM;

/**
 * Record InterventionNumberCounterRecord.
 *
 * Per-organization allocator for the human-readable intervention number
 * (rendered as `FG-{number}` by the client). One row per organization holds
 * the last allocated value; allocation happens through an atomic upsert in the
 * workflow gateway so concurrent creations never collide, unlike a `MAX()+1`
 * read that races before the new row exists.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'intervention_number_counters')]
class InterventionNumberCounterRecord
{
  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property lastNumber.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'last_number', type: 'integer')]
  public int $lastNumber = 0;
}
