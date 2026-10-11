<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Seed;

/**
 * Class SeedReference
 *
 * Carries identifiers and non-sensitive scalar fixture facts without publishing persistence objects.
 *
 * @category Contract
 */
final readonly class SeedReference
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $id fixture row identifier
   * @param string $type owning module's persisted type literal
   * @param string $status owning module's persisted status literal
   * @param string|null $facilityId optional placement identity
   * @param string|null $name optional display name
   *
   * @return void
   */
  public function __construct(public string $id, public string $type = '', public string $status = '', public ?string $facilityId = null, public ?string $name = null)
  {
  }
  // #endregion
}
