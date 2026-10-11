<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\UploadOrganizationLogo;

use Shared\Application\Message\ResultMessage;

/**
 * Result UploadOrganizationLogoResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UploadOrganizationLogoResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $logoUrl the committed canonical public URL
   */
  public function __construct(public string $organizationId, public string $logoUrl)
  {
  }
  // #endregion
}
