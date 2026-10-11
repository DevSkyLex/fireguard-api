<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\UploadOrganizationLogo;

use Shared\Application\Message\CommandMessage;

/**
 * Command UploadOrganizationLogoCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UploadOrganizationLogoCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $actingUserId the authenticated actor identifier
   * @param string $contents the uploaded image bytes
   * @param string $publicBaseUrl the server-owned origin for the public logo route
   */
  public function __construct(public string $organizationId, public string $actingUserId, public string $contents, public string $publicBaseUrl)
  {
  }
  // #endregion
}
