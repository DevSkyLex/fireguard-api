<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\AuthorizeOrganizationLogoUpload;

use Organization\Application\Contract\Exception\OrganizationAccessDeniedException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\{QueryHandler, VoidResult};

/**
 * Handler AuthorizeOrganizationLogoUploadHandler.
 *
 * Preserves permission refusal before the HTTP multipart parser inspects bytes.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuthorizeOrganizationLogoUploadHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationAuthorizationPort $authorization the owner's permission capability
   */
  public function __construct(private OrganizationAuthorizationPort $authorization)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param AuthorizeOrganizationLogoUploadQuery $query the permission request
   *
   * @return VoidResult the authorized result
   */
  public function __invoke(AuthorizeOrganizationLogoUploadQuery $query): VoidResult
  {
    if (!$this->authorization->hasPermission($query->userId, $query->organizationId, 'organization.settings.write')) {
      throw OrganizationAccessDeniedException::missingPermission('organization.settings.write');
    }

    return new VoidResult();
  }
  // #endregion
}
