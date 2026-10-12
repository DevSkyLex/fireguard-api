<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Query\GetImportTemplate;

use Import\Application\Port\Outbound\Internal\ImportKindRegistryPort;
use Import\Domain\Exception\{ImportAccessDeniedException, ImportJobNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/** Handler GetImportTemplateHandler. Empty templates never provision example resources. */
final readonly class GetImportTemplateHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Initializes the GetImportTemplateHandler dependencies and state.
   *
   * @access public
   *
   * @param OrganizationAuthorizationPort $authorization the authorization
   *
   * @return void
   */
  public function __construct(private OrganizationAuthorizationPort $authorization, private ImportKindRegistryPort $kinds)
  {
  }

  // #endregion
  // #region Methods
  /**
   * Method __invoke
   *
   * Executes the use case represented by GetImportTemplateHandler and returns its result.
   *
   * @access public
   *
   * @param GetImportTemplateQuery $query the query to execute
   *
   * @return GetImportTemplateResult
   */
  public function __invoke(GetImportTemplateQuery $query): GetImportTemplateResult
  {
    $descriptor = ($this->kinds->find($query->kind) ?? throw ImportJobNotFoundException::forOrganizationScope($query->organizationId))->descriptor();
    $permission = $descriptor->writePermission;
    $access = $this->authorization->resolveAccess($query->userId, $query->organizationId, $permission);
    if ($access->isOutsideScope()) {
      throw ImportJobNotFoundException::forOrganizationScope($query->organizationId);
    }
    if (!$access->isGranted()) {
      throw ImportAccessDeniedException::missingPermission($permission);
    }

    return new GetImportTemplateResult($descriptor->templateFilename, $descriptor->templateContent);
  }
  // #endregion
}
