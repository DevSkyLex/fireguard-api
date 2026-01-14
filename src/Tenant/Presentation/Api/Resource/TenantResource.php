<?php

declare(strict_types=1);

namespace Tenant\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Post};
use Tenant\Presentation\Api\Dto\Input\Tenant\TenantInput;
use Tenant\Presentation\Api\Dto\Output\Tenant\TenantOutput;
use Tenant\Presentation\Api\Operation\TenantOperations;
use Tenant\Presentation\Api\Processor\Tenant\CreateTenantProcessor;
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
    ),
    new Get(
      name: TenantOperations::GET,
      description: 'Get details of a specific tenant.',
      uriTemplate: '/tenants/{id}',
      input: false,
      output: TenantOutput::class,
      provider: GetTenantProvider::class,
      normalizationContext: ['groups' => [TenantSerializationGroup::READ]],
    ),
    new GetCollection(
      name: TenantOperations::LIST,
      description: 'Returns a list of all tenants. Requires ROLE_ADMIN.',
      uriTemplate: '/tenants',
      input: false,
      output: TenantOutput::class,
      provider: ListTenantsProvider::class,
      normalizationContext: ['groups' => [TenantSerializationGroup::READ]],
    ),
  ],
)]
final class TenantResource
{
}
