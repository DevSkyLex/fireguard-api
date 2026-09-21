<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Query\GetImportTemplate;

use Import\Application\Service\ImportPermissions;
use Import\Domain\Exception\{ImportAccessDeniedException, ImportJobNotFoundException};
use Import\Domain\ValueObject\ImportKind;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/** Handler GetImportTemplateHandler. Empty templates never provision example resources. */
final readonly class GetImportTemplateHandler implements QueryHandler
{
  public function __construct(private OrganizationAuthorizationPort $authorization)
  {
  }

  public function __invoke(GetImportTemplateQuery $query): GetImportTemplateResult
  {
    $kind = ImportKind::tryFrom($query->kind) ?? throw ImportJobNotFoundException::forOrganizationScope($query->organizationId);
    $permission = ImportPermissions::write($kind);
    $access = $this->authorization->resolveAccess($query->userId, $query->organizationId, $permission);
    if ($access->isOutsideScope()) {
      throw ImportJobNotFoundException::forOrganizationScope($query->organizationId);
    }
    if (!$access->isGranted()) {
      throw ImportAccessDeniedException::missingPermission($permission);
    }
    $header = match ($kind) {
      ImportKind::EQUIPMENT => 'type,subType,brand,model,serialNumber,locationLabel,facilityCode',
      ImportKind::FACILITY => 'type,name,code,address,latitude,longitude,parentCode',
      ImportKind::MEMBER => 'email,roles',
    };

    return new GetImportTemplateResult('fireguard-' . $kind->value . '-template.csv', $header . "\r\n");
  }
}
