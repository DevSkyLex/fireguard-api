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
      parameters: [
        'page' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'integer'],
          description: 'Page number',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'page', in: 'query', required: false, description: 'Page number', schema: ['type' => 'integer']),
        ),
        'itemsPerPage' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'integer'],
          description: 'Items per page',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'itemsPerPage', in: 'query', required: false, description: 'Items per page', schema: ['type' => 'integer']),
        ),
        'action' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::FILTER_BY_ACTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'action', in: 'query', required: false, description: self::FILTER_BY_ACTION, schema: ['type' => 'string']),
        ),
        'actorType' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::FILTER_BY_ACTOR_TYPE,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'actorType', in: 'query', required: false, description: self::FILTER_BY_ACTOR_TYPE, schema: ['type' => 'string']),
        ),
        'actorId' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::FILTER_BY_ACTOR_ID,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'actorId', in: 'query', required: false, description: self::FILTER_BY_ACTOR_ID, schema: ['type' => 'string']),
        ),
        'actorEmailHash' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: 'Filter by actor email hash',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'actorEmailHash', in: 'query', required: false, description: 'Filter by actor email hash', schema: ['type' => 'string']),
        ),
        'subjectType' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::FILTER_BY_SUBJECT_TYPE,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'subjectType', in: 'query', required: false, description: self::FILTER_BY_SUBJECT_TYPE, schema: ['type' => 'string']),
        ),
        'subjectId' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::FILTER_BY_SUBJECT_ID,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'subjectId', in: 'query', required: false, description: self::FILTER_BY_SUBJECT_ID, schema: ['type' => 'string']),
        ),
        'clientId' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::FILTER_BY_CLIENT_ID,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'clientId', in: 'query', required: false, description: self::FILTER_BY_CLIENT_ID, schema: ['type' => 'string']),
        ),
        'tenantId' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::FILTER_BY_TENANT_ID,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'tenantId', in: 'query', required: false, description: self::FILTER_BY_TENANT_ID, schema: ['type' => 'string']),
        ),
        'ipHash' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::FILTER_BY_IP_HASH,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'ipHash', in: 'query', required: false, description: self::FILTER_BY_IP_HASH, schema: ['type' => 'string']),
        ),
        'from' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'format' => 'date-time'],
          description: self::START_DATETIME,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'from', in: 'query', required: false, description: self::START_DATETIME, schema: ['type' => 'string', 'format' => 'date-time']),
        ),
        'to' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'format' => 'date-time'],
          description: self::END_DATETIME,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'to', in: 'query', required: false, description: self::END_DATETIME, schema: ['type' => 'string', 'format' => 'date-time']),
        ),
      ],
      openapi: new Operation(
        tags: ['Audit'],
        summary: 'List audit events',
        description: 'Retrieve a paginated list of audit events with optional filters.',
        security: [['bearerAuth' => []]],
        parameters: [],
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
      parameters: [
        'action' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::FILTER_BY_ACTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'action', in: 'query', required: false, description: self::FILTER_BY_ACTION, schema: ['type' => 'string']),
        ),
        'actorType' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::FILTER_BY_ACTOR_TYPE,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'actorType', in: 'query', required: false, description: self::FILTER_BY_ACTOR_TYPE, schema: ['type' => 'string']),
        ),
        'actorId' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::FILTER_BY_ACTOR_ID,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'actorId', in: 'query', required: false, description: self::FILTER_BY_ACTOR_ID, schema: ['type' => 'string']),
        ),
        'subjectType' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::FILTER_BY_SUBJECT_TYPE,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'subjectType', in: 'query', required: false, description: self::FILTER_BY_SUBJECT_TYPE, schema: ['type' => 'string']),
        ),
        'subjectId' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::FILTER_BY_SUBJECT_ID,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'subjectId', in: 'query', required: false, description: self::FILTER_BY_SUBJECT_ID, schema: ['type' => 'string']),
        ),
        'clientId' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::FILTER_BY_CLIENT_ID,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'clientId', in: 'query', required: false, description: self::FILTER_BY_CLIENT_ID, schema: ['type' => 'string']),
        ),
        'tenantId' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::FILTER_BY_TENANT_ID,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'tenantId', in: 'query', required: false, description: self::FILTER_BY_TENANT_ID, schema: ['type' => 'string']),
        ),
        'ipHash' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::FILTER_BY_IP_HASH,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'ipHash', in: 'query', required: false, description: self::FILTER_BY_IP_HASH, schema: ['type' => 'string']),
        ),
        'from' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'format' => 'date-time'],
          description: self::START_DATETIME,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'from', in: 'query', required: false, description: self::START_DATETIME, schema: ['type' => 'string', 'format' => 'date-time']),
        ),
        'to' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'format' => 'date-time'],
          description: self::END_DATETIME,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'to', in: 'query', required: false, description: self::END_DATETIME, schema: ['type' => 'string', 'format' => 'date-time']),
        ),
      ],
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
        parameters: [],
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
  private const FILTER_BY_ACTION = 'Filter by action';

  private const FILTER_BY_ACTOR_TYPE = 'Filter by actor type';

  private const FILTER_BY_ACTOR_ID = 'Filter by actor id';

  private const FILTER_BY_SUBJECT_TYPE = 'Filter by subject type';

  private const FILTER_BY_SUBJECT_ID = 'Filter by subject id';

  private const FILTER_BY_CLIENT_ID = 'Filter by client id';

  private const FILTER_BY_TENANT_ID = 'Filter by tenant id';

  private const FILTER_BY_IP_HASH = 'Filter by IP hash';

  private const START_DATETIME = 'Start datetime (ISO 8601)';

  private const END_DATETIME = 'End datetime (ISO 8601)';
}
