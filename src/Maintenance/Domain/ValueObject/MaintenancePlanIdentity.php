<?php

declare(strict_types=1);

namespace Maintenance\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * Class MaintenancePlanIdentity
 *
 * Identifies one operation within its organization and equipment ownership scope.
 *
 * @category ValueObject
 */
final readonly class MaintenancePlanIdentity
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Requires a complete, validated ownership scope for every plan.
   *
   * @access public
   *
   * @param string $id the plan UUID
   * @param string $organizationId the owning organization UUID
   * @param string $equipmentId the equipment UUID within that organization
   *
   * @return void
   */
  public function __construct(public string $id, public string $organizationId, public string $equipmentId)
  {
    Uuid::assertValid($id);
    Uuid::assertValid($organizationId);
    Uuid::assertValid($equipmentId);
  }
  // #endregion
}
