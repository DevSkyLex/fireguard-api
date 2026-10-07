<?php

declare(strict_types=1);

namespace Customer\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Patch, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Customer\Presentation\Api\Dto\Input\{ChangeCustomerInput, CreateCustomerInput};
use Customer\Presentation\Api\Dto\Output\CustomerOutput;
use Customer\Presentation\Api\Operation\CustomerOperations;
use Customer\Presentation\Api\Processor\CustomerProcessor;
use Customer\Presentation\Api\Provider\CustomerProvider;

/** Class CustomerResource. Internal customer management inside a single organization. @category Resource */
#[ApiResource(
  shortName: 'Customer',
  routePrefix: '/organizations',
  security: "is_granted('ROLE_USER')",
  normalizationContext: ['groups' => ['customer:read']],
  denormalizationContext: ['groups' => ['customer:write']],
  operations: [
    new GetCollection(name: CustomerOperations::LIST, uriTemplate: '/{organizationId}/customers', output: CustomerOutput::class, provider: CustomerProvider::class, openapi: new Operation(tags: ['Customer'], summary: 'Search internal customers', parameters: [new Parameter(name: 'search', in: 'query', schema: ['type' => 'string', 'maxLength' => 160]), new Parameter(name: 'archived', in: 'query', schema: ['type' => 'boolean', 'default' => false]), new Parameter(name: 'page', in: 'query', schema: ['type' => 'integer', 'minimum' => 1]), new Parameter(name: 'itemsPerPage', in: 'query', schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 100])])),
    new Get(name: CustomerOperations::GET, uriTemplate: '/{organizationId}/customers/{id}', output: CustomerOutput::class, provider: CustomerProvider::class, openapi: new Operation(tags: ['Customer'], summary: 'Read an internal customer')),
    new Post(name: CustomerOperations::CREATE, uriTemplate: '/{organizationId}/customers', status: 201, read: false, input: CreateCustomerInput::class, output: CustomerOutput::class, processor: CustomerProcessor::class, openapi: new Operation(tags: ['Customer'], summary: 'Create an internal customer')),
    new Patch(name: CustomerOperations::PATCH, uriTemplate: '/{organizationId}/customers/{id}', status: 200, read: false, input: ChangeCustomerInput::class, output: CustomerOutput::class, processor: CustomerProcessor::class, openapi: new Operation(tags: ['Customer'], summary: 'Update an internal customer', parameters: [new Parameter(name: 'If-Match', in: 'header', required: true, schema: ['type' => 'string', 'example' => '"revision-1"'])], responses: [200 => new Response(description: 'Customer updated'), 403 => new Response(description: 'Permission denied'), 404 => new Response(description: 'Customer not found'), 412 => new Response(description: 'Stale revision'), 428 => new Response(description: 'If-Match required')])),
    new Post(name: CustomerOperations::ARCHIVE, uriTemplate: '/{organizationId}/customers/{id}/archive', status: 200, read: false, input: false, output: CustomerOutput::class, processor: CustomerProcessor::class, openapi: new Operation(tags: ['Customer'], summary: 'Archive an internal customer', parameters: [new Parameter(name: 'If-Match', in: 'header', required: true, schema: ['type' => 'string'])])),
    new Post(name: CustomerOperations::RESTORE, uriTemplate: '/{organizationId}/customers/{id}/restore', status: 200, read: false, input: false, output: CustomerOutput::class, processor: CustomerProcessor::class, openapi: new Operation(tags: ['Customer'], summary: 'Restore an internal customer', parameters: [new Parameter(name: 'If-Match', in: 'header', required: true, schema: ['type' => 'string'])])),
  ],
)]
final class CustomerResource
{
}
