<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use ApiPlatform\OpenApi\Model\Operation;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationPermissionOutput;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Provider\Organization\ListOrganizationPermissionsProvider;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;

/**
 * Resource OrganizationPermissionResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OrganizationPermission',
  routePrefix: '/organizations',
  description: 'Organization-scoped permission catalog (read-only).',
  operations: [
    new GetCollection(
      name: OrganizationOperations::LIST_ORGANIZATION_PERMISSIONS,
      uriTemplate: '/{organizationId}/permissions',
      input: false,
      output: OrganizationPermissionOutput::class,
      provider: ListOrganizationPermissionsProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Permissions'],
        summary: 'List available Organization permissions',
        description: 'Lists all system-defined permissions that can be assigned to organization roles.',
      ),
    ),
  ],
)]
final class OrganizationPermissionResource
{
}
