<?php

declare(strict_types=1);

namespace Facility\Infrastructure\DataFixtures;

use DateTimeImmutable;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;

/** Required identity and description of one fixture facility. */
final readonly class FacilitySeed
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the identity, hierarchy, descriptive fields, and creation time used to seed one facility.
   *
   * @access public
   *
   * @param string $id identifier assigned to the fixture facility
   * @param ?FacilityRecord $parentFacility parent record, or null for a root facility
   * @param string $type facility type seeded for this record
   * @param string $name display name assigned to the facility
   * @param ?string $code optional facility code
   * @param DateTimeImmutable $createdAt creation time assigned to the fixture record
   *
   * @return void
   */
  public function __construct(
    public string $id,
    public ?FacilityRecord $parentFacility,
    public string $type,
    public string $name,
    public ?string $code,
    public DateTimeImmutable $createdAt,
  ) {
  }
  // #endregion
}
