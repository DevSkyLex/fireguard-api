<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\UpdateOrganizationSettings;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase UpdateOrganizationSettingsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateOrganizationSettingsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * UpdateOrganizationSettingsResult class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the updated organization identifier
   */
  public function __construct(
    public string $organizationId,
  ) {
  }
  // #endregion
}
