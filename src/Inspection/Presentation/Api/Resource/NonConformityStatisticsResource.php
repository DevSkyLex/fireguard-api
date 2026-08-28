<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Inspection\Presentation\Api\Dto\Output\Statistics\NonConformityStatisticsOutput;
use Inspection\Presentation\Api\Operation\InspectionOperations;
use Inspection\Presentation\Api\Provider\Statistics\GetNonConformityStatisticsProvider;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource NonConformityStatisticsResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'NonConformityStatistics',
  routePrefix: '/organizations',
  description: 'Organization-wide non-conformity statistics for the compliance KPI surfaces.',
  operations: [
    new Get(
      name: InspectionOperations::GET_NON_CONFORMITY_STATISTICS,
      uriTemplate: '/{organizationId}/non-conformities/statistics',
      input: false,
      output: NonConformityStatisticsOutput::class,
      provider: GetNonConformityStatisticsProvider::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'Get non-conformity statistics',
        description: 'Organization-wide snapshot: `bySeverity` (all four severity keys, each with `open`/`resolved` counts, zeros included — open is status `open`/`in_progress`, resolved is `done`/`waived`), `byFacility` (top 10 facilities by open count: `id`, `name`, `open`, `critical`), `byEquipmentType` (top 10 equipment types by open count: `type`, `open`), `resolution` (`averageDays` and `medianDays` over `resolvedAt - createdAt` in fractional days, null when nothing resolved), and `slaBreachedOpen` (unresolved rows whose SLA breach has been stamped). The optional `from`/`to` window filters on the non-conformity `createdAt`. Requires `organization.inspection.read`, resolved once via resolveAccess: a caller outside the organization scope gets 404 (never confirming the organization exists), an unentitled member gets 403.',
        parameters: [
          new Parameter(name: 'from', in: 'query', description: 'Inclusive ISO 8601 datetime lower bound applied to the non-conformity createdAt.', required: false, schema: ['type' => 'string', 'format' => 'date-time', 'example' => '2026-03-01T00:00:00Z']),
          new Parameter(name: 'to', in: 'query', description: 'Inclusive ISO 8601 datetime upper bound applied to the non-conformity createdAt.', required: false, schema: ['type' => 'string', 'format' => 'date-time', 'example' => '2026-03-31T23:59:59Z']),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Non-conformity statistics retrieved'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Unparseable from/to bound, or from after to'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Member without organization.inspection.read'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization outside the caller\'s scope — deliberately not 403, which would confirm it exists'),
        ],
      ),
    ),
  ],
)]
final class NonConformityStatisticsResource
{
}
