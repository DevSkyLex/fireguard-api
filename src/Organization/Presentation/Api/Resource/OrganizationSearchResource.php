<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationSearchOutput;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Provider\Organization\SearchOrganizationProvider;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource OrganizationSearchResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OrganizationSearch',
  routePrefix: '/organizations',
  description: 'Organization-wide global search across equipment, facilities, interventions, inspections and non-conformities.',
  operations: [
    new Get(
      name: OrganizationOperations::SEARCH_ORGANIZATION,
      uriTemplate: '/{organizationId}/search',
      input: false,
      output: OrganizationSearchOutput::class,
      provider: SearchOrganizationProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Search across an organization',
        description: 'Case-insensitive free-text search returning a flat `results` list, grouped by type in a stable order (`equipment`, `facility`, `intervention`, `inspection`, `non_conformity`), with at most 5 hits per type ordered by most recent update. Each hit carries `type`, `id`, `title` and optional `subtitle`/`extra`; the frontend builds the target route from `type` + `id` — the API ships no URL. Matched fields per type: equipment type/brand/model/serial number/location label; facility name/code/address; intervention name/number; inspection checklist reference code or inspection id; non-conformity description. The caller must be an ACTIVE member of the organization (a non-member gets 404, never a hint the organization exists); each type is then individually soft-gated on its read permission (`organization.equipment.read`, `organization.facilities.read`, `organization.interventions.read`, `organization.inspection.read` for both inspections and non-conformities) — a member without a permission simply gets no rows of that type, never an error.',
        parameters: [
          new Parameter(name: 'q', in: 'query', description: 'The free-text search term, 2 to 100 characters after trimming.', required: true, schema: ['type' => 'string', 'minLength' => 2, 'maxLength' => 100]),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Search results retrieved'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Missing q parameter, or q shorter than 2 / longer than 100 characters'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization not found or the caller is not an active member — deliberately not 403, which would confirm it exists'),
        ],
      ),
    ),
  ],
)]
final class OrganizationSearchResource
{
}
