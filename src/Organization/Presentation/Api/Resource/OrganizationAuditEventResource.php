<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationAuditEventOutput;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Provider\Organization\ListOrganizationAuditEventsProvider;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;

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
  ],
)]
final class OrganizationAuditEventResource
{
}
