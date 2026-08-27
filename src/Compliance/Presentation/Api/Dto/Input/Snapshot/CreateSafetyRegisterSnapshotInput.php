<?php

declare(strict_types=1);

namespace Compliance\Presentation\Api\Dto\Input\Snapshot;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Dto CreateSafetyRegisterSnapshotInput.
 *
 * Body of `POST /organizations/{organizationId}/compliance/register-snapshots`.
 * An empty body (or a null `facilityId`) archives the organization-wide
 * register; a `facilityId` archives that facility's register.
 *
 * @category Dto
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreateSafetyRegisterSnapshotInput
{
  // #region Properties
  /**
   * Property facilityId.
   *
   * @since 1.0.0
   */
  #[Assert\Uuid(message: 'The facility identifier must be a valid UUID.')]
  public ?string $facilityId = null;
  // #endregion
}
