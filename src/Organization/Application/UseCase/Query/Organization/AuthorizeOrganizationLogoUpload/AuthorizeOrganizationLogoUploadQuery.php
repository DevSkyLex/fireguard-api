<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\AuthorizeOrganizationLogoUpload;

use Shared\Application\Message\QueryMessage;

/**
 * Query AuthorizeOrganizationLogoUploadQuery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuthorizeOrganizationLogoUploadQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization scope
   * @param string $userId the authenticated actor
   */
  public function __construct(public string $organizationId, public string $userId)
  {
  }
  // #endregion
}
