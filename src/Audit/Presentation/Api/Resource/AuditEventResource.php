<?php

declare(strict_types=1);

namespace Audit\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Audit\Application\UseCase\Query\ExportAuditEvents\ExportAuditEventsHandler;
use Audit\Presentation\Api\Controller\ExportAuditEventsController;
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
      requirements: ['id' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'],
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
    new Get(
      name: AuditOperations::EXPORT,
      description: 'Streams a bounded CSV export of audit events using the same filters as the list endpoint.',
      uriTemplate: '/audit-events/export',
      controller: ExportAuditEventsController::class,
      read: false,
      write: false,
      deserialize: false,
      serialize: false,
      output: false,
      security: "is_granted('audit.export')",
      openapi: new Operation(
        tags: ['Audit'],
        summary: 'Export audit events (CSV)',
        description: 'Streams a CSV export of audit events (Content-Disposition: attachment) using the same filters '
          . 'as the list endpoint. Bounded to ' . ExportAuditEventsHandler::MAX_EXPORT_ROWS . ' matching rows — the '
          . 'request is rejected with 422 if the filters match more; narrow with a shorter from/to range or a more '
          . 'specific filter and retry. The CSV contains exactly the same fields already exposed by the JSON list/get '
          . 'endpoints (including actorEmail/ipAddress, which are only non-null when SECURITY_LOG_INCLUDE_PII was true '
          . 'at the time the event was recorded) — no additional data is exposed and no extra redaction is applied '
          . 'beyond what the ledger already stores.',
        security: [['bearerAuth' => []]],
        parameters: [
          new Parameter(name: 'action', in: 'query', required: false, description: 'Filter by action', schema: ['type' => 'string']),
          new Parameter(name: 'actorType', in: 'query', required: false, description: 'Filter by actor type', schema: ['type' => 'string']),
          new Parameter(name: 'actorId', in: 'query', required: false, description: 'Filter by actor id', schema: ['type' => 'string']),
          new Parameter(name: 'subjectType', in: 'query', required: false, description: 'Filter by subject type', schema: ['type' => 'string']),
          new Parameter(name: 'subjectId', in: 'query', required: false, description: 'Filter by subject id', schema: ['type' => 'string']),
          new Parameter(name: 'clientId', in: 'query', required: false, description: 'Filter by client id', schema: ['type' => 'string']),
          new Parameter(name: 'tenantId', in: 'query', required: false, description: 'Filter by tenant id', schema: ['type' => 'string']),
          new Parameter(name: 'ipHash', in: 'query', required: false, description: 'Filter by IP hash', schema: ['type' => 'string']),
          new Parameter(name: 'from', in: 'query', required: false, description: 'Start datetime (ISO 8601)', schema: ['type' => 'string', 'format' => 'date-time']),
          new Parameter(name: 'to', in: 'query', required: false, description: 'End datetime (ISO 8601)', schema: ['type' => 'string', 'format' => 'date-time']),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'CSV export streamed successfully'),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(description: 'Export exceeds the row cap; narrow the filters and retry'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions (requires audit.export)'),
        ],
      ),
    ),
  ],
)]
final class AuditEventResource
{
}
