<?php

declare(strict_types=1);

namespace ServiceRequest\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Patch, Post, QueryParameter};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use ServiceRequest\Presentation\Api\Dto\Input\{ConvertServiceRequestInput, CreateServiceRequestInput, DecisionServiceRequestInput, QualifyServiceRequestInput, UpdateServiceRequestInput};
use ServiceRequest\Presentation\Api\Dto\Output\ServiceRequestOutput;
use ServiceRequest\Presentation\Api\Operation\ServiceRequestOperations;
use ServiceRequest\Presentation\Api\Processor\ServiceRequestProcessor;
use ServiceRequest\Presentation\Api\Provider\ServiceRequestProvider;

/** Class ServiceRequestResource. Organization-owned internal repair request workflow. @category Resource */
#[ApiResource(shortName: 'ServiceRequest', routePrefix: '/organizations', security: "is_granted('ROLE_USER')", normalizationContext: ['groups' => ['service_request:read']], denormalizationContext: ['groups' => ['service_request:write']], operations: [
  new GetCollection(name: ServiceRequestOperations::LIST, uriTemplate: '/{organizationId}/service-requests', input: false, output: ServiceRequestOutput::class, provider: ServiceRequestProvider::class, paginationEnabled: true, paginationClientItemsPerPage: true, paginationMaximumItemsPerPage: 100, paginationItemsPerPage: 30, parameters: [
    'status' => new QueryParameter(schema: ['type' => 'string', 'enum' => ['requested', 'qualified', 'rejected', 'cancelled', 'converted']], required: false, castToArray: false, constraints: []),
    'equipmentId' => new QueryParameter(schema: ['type' => 'string', 'format' => 'uuid'], required: false, castToArray: false, constraints: []),
    'siteId' => new QueryParameter(schema: ['type' => 'string', 'format' => 'uuid'], required: false, castToArray: false, constraints: []),
    'search' => new QueryParameter(schema: ['type' => 'string', 'maxLength' => 160], required: false, castToArray: false, constraints: []),
  ], openapi: new Operation(tags: ['ServiceRequest'], summary: 'list an internal repair request')),
  new Get(name: ServiceRequestOperations::GET, uriTemplate: '/{organizationId}/service-requests/{id}', input: false, output: ServiceRequestOutput::class, provider: ServiceRequestProvider::class, openapi: new Operation(tags: ['ServiceRequest'], summary: 'get an internal repair request')),
  new Post(name: ServiceRequestOperations::CREATE, uriTemplate: '/{organizationId}/service-requests', input: CreateServiceRequestInput::class, output: ServiceRequestOutput::class, status: 201, read: false, processor: ServiceRequestProcessor::class, openapi: new Operation(tags: ['ServiceRequest'], summary: 'create an internal repair request')),
  new Patch(name: ServiceRequestOperations::PATCH, uriTemplate: '/{organizationId}/service-requests/{id}', input: UpdateServiceRequestInput::class, output: ServiceRequestOutput::class, status: 200, read: false, processor: ServiceRequestProcessor::class, openapi: new Operation(tags: ['ServiceRequest'], summary: 'patch an internal repair request', parameters: [new Parameter(name: 'If-Match', in: 'header', required: true, schema: ['type' => 'string', 'example' => self::REVISION_EXAMPLE])])),
  new Post(name: ServiceRequestOperations::QUALIFY, uriTemplate: '/{organizationId}/service-requests/{id}/qualify', input: QualifyServiceRequestInput::class, output: ServiceRequestOutput::class, status: 200, read: false, processor: ServiceRequestProcessor::class, openapi: new Operation(tags: ['ServiceRequest'], summary: 'qualify an internal repair request', parameters: [new Parameter(name: 'If-Match', in: 'header', required: true, schema: ['type' => 'string', 'example' => self::REVISION_EXAMPLE])])),
  new Post(name: ServiceRequestOperations::REJECT, uriTemplate: '/{organizationId}/service-requests/{id}/reject', input: DecisionServiceRequestInput::class, output: ServiceRequestOutput::class, status: 200, read: false, processor: ServiceRequestProcessor::class, openapi: new Operation(tags: ['ServiceRequest'], summary: 'reject an internal repair request', parameters: [new Parameter(name: 'If-Match', in: 'header', required: true, schema: ['type' => 'string', 'example' => self::REVISION_EXAMPLE])])),
  new Post(name: ServiceRequestOperations::CANCEL, uriTemplate: '/{organizationId}/service-requests/{id}/cancel', input: DecisionServiceRequestInput::class, output: ServiceRequestOutput::class, status: 200, read: false, processor: ServiceRequestProcessor::class, openapi: new Operation(tags: ['ServiceRequest'], summary: 'cancel an internal repair request', parameters: [new Parameter(name: 'If-Match', in: 'header', required: true, schema: ['type' => 'string', 'example' => self::REVISION_EXAMPLE])])),
  new Post(name: ServiceRequestOperations::CONVERT, uriTemplate: '/{organizationId}/service-requests/{id}/convert', input: ConvertServiceRequestInput::class, output: ServiceRequestOutput::class, status: 200, read: false, processor: ServiceRequestProcessor::class, openapi: new Operation(tags: ['ServiceRequest'], summary: 'convert an internal repair request', parameters: [new Parameter(name: 'If-Match', in: 'header', required: true, schema: ['type' => 'string', 'example' => self::REVISION_EXAMPLE])])),
])]
final class ServiceRequestResource
{
  // #region Constants
  /**
   * Constant REVISION_EXAMPLE
   *
   * Keeps the required optimistic precondition example consistent across lifecycle operations.
   */
  private const string REVISION_EXAMPLE = '"revision-1"';
  // #endregion
}
