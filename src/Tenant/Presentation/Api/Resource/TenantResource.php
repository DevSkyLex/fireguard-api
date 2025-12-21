<?php

declare(strict_types=1);

namespace Tenant\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Post};
use Tenant\Presentation\Api\Dto\TenantInput;
use Tenant\Presentation\Api\Dto\TenantOutput;
use Tenant\Presentation\Api\Processor\CreateTenantProcessor;
use Tenant\Presentation\Api\Provider\GetTenantProvider;
use Tenant\Presentation\Api\Provider\ListTenantsProvider;

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
      name: 'tenant_create',
      description: 'Create a new tenant with custom OAuth2 settings.',
      uriTemplate: '/tenants',
      input: TenantInput::class,
      output: TenantOutput::class,
      processor: CreateTenantProcessor::class
    ),
    new Get(
      name: 'tenant_get',
      description: 'Get details of a specific tenant.',
      uriTemplate: '/tenants/{id}',
      input: false,
      output: TenantOutput::class,
      provider: GetTenantProvider::class
    ),
    new GetCollection(
      name: 'tenant_list',
      description: 'Returns a list of all tenants. Requires ROLE_ADMIN.',
      uriTemplate: '/tenants',
      input: false,
      output: TenantOutput::class,
      provider: ListTenantsProvider::class
    ),
  ]
)]
final class TenantResource
{
}
