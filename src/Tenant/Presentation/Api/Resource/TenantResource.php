<?php

declare(strict_types=1);

namespace Tenant\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post, Put};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Tenant\Presentation\Api\Dto\Input\Tenant\TenantInput;
use Tenant\Presentation\Api\Dto\Output\Tenant\TenantOutput;
use Tenant\Presentation\Api\Operation\TenantOperations;
use Tenant\Presentation\Api\Processor\Tenant\{
  ActivateTenantProcessor,
  CreateTenantProcessor,
  DeactivateTenantProcessor,
  DeleteTenantProcessor,
  UpdateTenantProcessor
};
use Tenant\Presentation\Api\Provider\Tenant\{GetTenantProvider, ListTenantsProvider};
use Tenant\Presentation\Api\Serialization\TenantSerializationGroup;

/**
 * Resource TenantResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Tenant',
  description: 'Multi-tenant configuration management. Tenants define OAuth2 settings for different organizations.',
  operations: [
    new Post(
      name: TenantOperations::CREATE,
      description: 'Create a new tenant with custom OAuth2 settings.',
      uriTemplate: '/tenants',
      input: TenantInput::class,
      output: TenantOutput::class,
      processor: CreateTenantProcessor::class,
      denormalizationContext: ['groups' => [TenantSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [TenantSerializationGroup::READ]],
      security: "is_granted('tenants.create')",
      openapi: new Operation(
        tags: ['Tenants'],
        summary: 'Create tenant',
        description: 'Create a new tenant with custom OAuth2 settings.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_CREATED => new Response(
            description: 'Tenant created successfully',
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request - validation failed',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions',
          ),
        ],
      ),
    ),
    new Get(
      name: TenantOperations::GET,
      description: 'Get details of a specific tenant.',
      uriTemplate: '/tenants/{id}',
      input: false,
      output: TenantOutput::class,
      provider: GetTenantProvider::class,
      normalizationContext: ['groups' => [TenantSerializationGroup::READ]],
      security: "is_granted('tenants.read')",
      openapi: new Operation(
        tags: ['Tenants'],
        summary: 'Get tenant',
        description: 'Get details of a specific tenant.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Tenant retrieved successfully',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Tenant not found',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions',
          ),
        ],
      ),
    ),
    new GetCollection(
      name: TenantOperations::LIST,
      description: 'Returns a list of all tenants. Requires tenants.read permission.',
      uriTemplate: '/tenants',
      input: false,
      output: TenantOutput::class,
      provider: ListTenantsProvider::class,
      normalizationContext: ['groups' => [TenantSerializationGroup::READ]],
      security: "is_granted('tenants.read')",
      openapi: new Operation(
        tags: ['Tenants'],
        summary: 'List tenants',
        description: 'Returns a list of all tenants. Requires tenants.read permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'List of tenants retrieved successfully',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions',
          ),
        ],
      ),
    ),
    new Patch(
      name: TenantOperations::UPDATE,
      description: 'Update tenant settings or name.',
      uriTemplate: '/tenants/{id}',
      input: TenantInput::class,
      output: TenantOutput::class,
      processor: UpdateTenantProcessor::class,
      denormalizationContext: ['groups' => [TenantSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [TenantSerializationGroup::READ]],
      security: "is_granted('tenants.update')",
      openapi: new Operation(
        tags: ['Tenants'],
        summary: 'Update tenant',
        description: 'Update a tenant by ID. Requires tenants.update permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Tenant updated successfully'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid request - validation failed'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Tenant not found'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Put(
      name: TenantOperations::REPLACE,
      description: 'Replace a tenant configuration.',
      uriTemplate: '/tenants/{id}',
      input: TenantInput::class,
      output: TenantOutput::class,
      processor: UpdateTenantProcessor::class,
      denormalizationContext: ['groups' => [TenantSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [TenantSerializationGroup::READ]],
      security: "is_granted('tenants.update')",
      openapi: new Operation(
        tags: ['Tenants'],
        summary: 'Replace tenant',
        description: 'Replace a tenant by ID. Requires tenants.update permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Tenant replaced successfully'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid request - validation failed'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Tenant not found'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Delete(
      name: TenantOperations::DELETE,
      description: 'Delete a tenant.',
      uriTemplate: '/tenants/{id}',
      input: false,
      output: false,
      processor: DeleteTenantProcessor::class,
      security: "is_granted('tenants.delete')",
      openapi: new Operation(
        tags: ['Tenants'],
        summary: 'Delete tenant',
        description: 'Delete a tenant by ID. Requires tenants.delete permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(description: 'Tenant deleted successfully'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Tenant not found'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Post(
      name: TenantOperations::ACTIVATE,
      description: 'Activate a tenant.',
      uriTemplate: '/tenants/{id}/activate',
      input: false,
      output: TenantOutput::class,
      processor: ActivateTenantProcessor::class,
      normalizationContext: ['groups' => [TenantSerializationGroup::READ]],
      security: "is_granted('tenants.update')",
      openapi: new Operation(
        tags: ['Tenants'],
        summary: 'Activate tenant',
        description: 'Activate a tenant by ID. Requires tenants.update permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Tenant activated successfully'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Tenant not found'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Post(
      name: TenantOperations::DEACTIVATE,
      description: 'Deactivate a tenant.',
      uriTemplate: '/tenants/{id}/deactivate',
      input: false,
      output: TenantOutput::class,
      processor: DeactivateTenantProcessor::class,
      normalizationContext: ['groups' => [TenantSerializationGroup::READ]],
      security: "is_granted('tenants.update')",
      openapi: new Operation(
        tags: ['Tenants'],
        summary: 'Deactivate tenant',
        description: 'Deactivate a tenant by ID. Requires tenants.update permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Tenant deactivated successfully'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Tenant not found'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
  ],
)]
final class TenantResource
{
}
