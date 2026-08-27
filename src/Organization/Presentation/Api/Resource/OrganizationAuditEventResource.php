<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Organization\Presentation\Api\Controller\ExportOrganizationAuditEventsController;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationAuditEventOutput;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Provider\Organization\ListOrganizationAuditEventsProvider;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource OrganizationAuditEventResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OrganizationAuditEvent',
  routePrefix: '/organizations',
  description: 'Organization-scoped audit ledger read (activity feed).',
  operations: [
    new GetCollection(
      name: OrganizationOperations::LIST_ORGANIZATION_AUDIT_EVENTS,
      uriTemplate: '/{organizationId}/audit-events',
      input: false,
      output: OrganizationAuditEventOutput::class,
      provider: ListOrganizationAuditEventsProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationItemsPerPage: 30,
      paginationMaximumItemsPerPage: 100,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'List organization audit events',
        description: 'Returns the organization\'s slice of the audit ledger, newest first, for the activity feed. Requires the `organization.audit.read` permission in the target organization (held by admins through `organization.*`). The payload is deliberately narrower than the platform-level audit API: actor email, IP address, user agent, and ledger chain internals are never exposed here, regardless of platform PII settings, and metadata keys carrying PII or request context are stripped.',
        parameters: [
          new Parameter(
            name: 'action',
            in: 'query',
            required: false,
            description: 'Optional exact audit action filter (e.g. organization.member_added).',
            schema: ['type' => 'string', 'example' => 'organization.member_added'],
          ),
          new Parameter(
            name: 'from',
            in: 'query',
            required: false,
            description: 'Optional inclusive ISO 8601 lower bound on the occurrence datetime.',
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => '2026-03-01T00:00:00Z'],
          ),
          new Parameter(
            name: 'to',
            in: 'query',
            required: false,
            description: 'Optional inclusive ISO 8601 upper bound on the occurrence datetime.',
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => '2026-03-29T23:59:59Z'],
          ),
        ],
      ),
    ),
    new Get(
      name: OrganizationOperations::EXPORT_ORGANIZATION_AUDIT_EVENTS,
      uriTemplate: '/{organizationId}/audit-events/export',
      controller: ExportOrganizationAuditEventsController::class,
      read: false,
      write: false,
      deserialize: false,
      serialize: false,
      output: false,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Export organization audit events (CSV)',
        description: 'Streams the organization\'s slice of the audit ledger as CSV, using the same filters as the list endpoint. Requires the `organization.audit.export` permission, which is deliberately separate from `organization.audit.read`: reading keeps the data inside the product, exporting takes a file out. The organization is taken from the URI and is not a filter the caller can widen — unlike the platform-level `/audit-events/export`, which builds its criteria from the request and is therefore reserved to platform operators. The columns match what the read endpoint exposes: no actor email, no IP address, no user agent, no ledger chain internals.',
        parameters: [
          new Parameter(
            name: 'action',
            in: 'query',
            required: false,
            description: 'Optional exact audit action filter (e.g. organization.member_added).',
            schema: ['type' => 'string', 'example' => 'organization.member_added'],
          ),
          new Parameter(
            name: 'from',
            in: 'query',
            required: false,
            description: 'Optional inclusive ISO 8601 lower bound on the occurrence datetime.',
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => '2026-03-01T00:00:00Z'],
          ),
          new Parameter(
            name: 'to',
            in: 'query',
            required: false,
            description: 'Optional inclusive ISO 8601 upper bound on the occurrence datetime.',
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => '2026-03-29T23:59:59Z'],
          ),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'CSV stream'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Malformed date filter'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Member without the organization.audit.export permission'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization not found, or the caller is not an active member'),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(description: 'The filters match more rows than the export cap'),
        ],
      ),
    ),
  ],
)]
final class OrganizationAuditEventResource
{
}
