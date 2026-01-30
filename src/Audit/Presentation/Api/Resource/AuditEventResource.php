<?php

declare(strict_types=1);

namespace Audit\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Audit\Presentation\Api\Dto\Output\AuditEvent\AuditEventOutput;
use Audit\Presentation\Api\Operation\AuditOperations;
use Audit\Presentation\Api\Provider\AuditEvent\{GetAuditEventProvider, ListAuditEventsProvider};
use Audit\Presentation\Api\Serialization\AuditSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource AuditEventResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'AuditEvent',
  description: 'Audit ledger events for security and compliance.',
  operations: [
    new GetCollection(
      name: AuditOperations::LIST,
      description: 'Returns a paginated list of audit events.',
      uriTemplate: '/audit-events',
      input: false,
      output: AuditEventOutput::class,
      provider: ListAuditEventsProvider::class,
      normalizationContext: ['groups' => [AuditSerializationGroup::READ]],
      security: "is_granted('audit.read')",
      openapi: new Operation(
        tags: ['Audit'],
        summary: 'List audit events',
        description: 'Retrieve a paginated list of audit events with optional filters.',
        security: [['bearerAuth' => []]],
        parameters: [
          new Parameter(name: 'page', in: 'query', required: false, description: 'Page number', schema: ['type' => 'integer']),
          new Parameter(name: 'itemsPerPage', in: 'query', required: false, description: 'Items per page', schema: ['type' => 'integer']),
          new Parameter(name: 'action', in: 'query', required: false, description: 'Filter by action', schema: ['type' => 'string']),
          new Parameter(name: 'actorType', in: 'query', required: false, description: 'Filter by actor type', schema: ['type' => 'string']),
          new Parameter(name: 'actorId', in: 'query', required: false, description: 'Filter by actor id', schema: ['type' => 'string']),
          new Parameter(name: 'actorEmailHash', in: 'query', required: false, description: 'Filter by actor email hash', schema: ['type' => 'string']),
          new Parameter(name: 'subjectType', in: 'query', required: false, description: 'Filter by subject type', schema: ['type' => 'string']),
          new Parameter(name: 'subjectId', in: 'query', required: false, description: 'Filter by subject id', schema: ['type' => 'string']),
          new Parameter(name: 'clientId', in: 'query', required: false, description: 'Filter by client id', schema: ['type' => 'string']),
          new Parameter(name: 'tenantId', in: 'query', required: false, description: 'Filter by tenant id', schema: ['type' => 'string']),
          new Parameter(name: 'ipHash', in: 'query', required: false, description: 'Filter by IP hash', schema: ['type' => 'string']),
          new Parameter(name: 'from', in: 'query', required: false, description: 'Start datetime (ISO 8601)', schema: ['type' => 'string', 'format' => 'date-time']),
          new Parameter(name: 'to', in: 'query', required: false, description: 'End datetime (ISO 8601)', schema: ['type' => 'string', 'format' => 'date-time']),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'List of audit events retrieved successfully'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions (requires audit.read)'),
        ],
      ),
    ),
    new Get(
      name: AuditOperations::GET,
      description: 'Get details of a specific audit event.',
      uriTemplate: '/audit-events/{id}',
      input: false,
      output: AuditEventOutput::class,
      provider: GetAuditEventProvider::class,
      normalizationContext: ['groups' => [AuditSerializationGroup::READ]],
      security: "is_granted('audit.read')",
      openapi: new Operation(
        tags: ['Audit'],
        summary: 'Get audit event',
        description: 'Retrieve a single audit event by ID.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Audit event retrieved successfully'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Audit event not found'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions (requires audit.read)'),
        ],
      ),
    ),
  ],
)]
final class AuditEventResource
{
}
