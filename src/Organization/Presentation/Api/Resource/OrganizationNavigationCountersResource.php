<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationNavigationCountersOutput;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Provider\Organization\GetOrganizationNavigationCountersProvider;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource OrganizationNavigationCountersResource.
 *
 * Lightweight badge counters for the frontend sidebar navigation (open
 * field interventions, open non-conformities). Deliberately its own
 * endpoint rather than reusing `/dashboard`: the dashboard payload is
 * expensive (KPIs, trends, comparisons) and is not meant to be polled by a
 * persistent UI chrome element.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OrganizationNavigationCounters',
  routePrefix: '/organizations',
  operations: [
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION_NAVIGATION_COUNTERS,
      uriTemplate: '/{organizationId}/navigation-counters',
      input: false,
      output: OrganizationNavigationCountersOutput::class,
      provider: GetOrganizationNavigationCountersProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Get organization sidebar navigation counters',
        description: 'Returns lightweight badge counters for the frontend sidebar navigation: `openInterventions` (field interventions not yet `published`/`abandoned`) and `openNonConformities` (non-conformities currently `open` or `in_progress`). The caller only needs to be an ACTIVE member of the organization; each counter individually falls back to 0 (never a 403) when the caller lacks the underlying read permission (`organization.interventions.read` / `organization.inspection.read` respectively), mirroring how the organization dashboard degrades its own interventions section.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Navigation counters retrieved successfully',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Organization not found or the authenticated user is not an active member',
          ),
        ],
      ),
    ),
  ],
)]
final class OrganizationNavigationCountersResource
{
}
