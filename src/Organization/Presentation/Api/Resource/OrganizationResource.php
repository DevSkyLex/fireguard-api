<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Organization\Presentation\Api\Dto\Input\Organization\CreateOrganizationInput;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationOutput;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Processor\Organization\CreateOrganizationProcessor;
use Organization\Presentation\Api\Provider\Organization\{GetOrganizationProvider, ListUserOrganizationsProvider};
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource OrganizationResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Organization',
  routePrefix: '/organizations',
  description: 'Organization management and membership entry points.',
  operations: [
    new Post(
      name: OrganizationOperations::CREATE_ORGANIZATION,
      uriTemplate: '',
      input: CreateOrganizationInput::class,
      output: OrganizationOutput::class,
      processor: CreateOrganizationProcessor::class,
      denormalizationContext: ['groups' => [OrganizationSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Create Organization',
        description: 'Creates a Organization and assigns the creator as owner.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Organization created'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
    new GetCollection(
      name: OrganizationOperations::LIST_USER_ORGANIZATIONS,
      uriTemplate: '',
      input: false,
      output: OrganizationOutput::class,
      provider: ListUserOrganizationsProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationItemsPerPage: 30,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'List user Organizations',
        description: 'Lists Organizations where the authenticated user is a member.',
        parameters: [
          new Parameter(
            name: 'status',
            in: 'query',
            required: false,
            description: 'Filter by organization status.',
            schema: ['type' => 'string', 'enum' => ['active', 'suspended', 'archived']],
          ),
        ],
      ),
    ),
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION,
      uriTemplate: '/{id}',
      input: false,
      output: OrganizationOutput::class,
      provider: GetOrganizationProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Get Organization',
        description: 'Returns Organization details if the user has Organization.read permission in that Organization.',
      ),
    ),
  ],
)]
final class OrganizationResource
{
}
