<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Intervention\Presentation\Api\Dto\Output\Statistics\InterventionStatisticsOutput;
use Intervention\Presentation\Api\Operation\InterventionOperations;
use Intervention\Presentation\Api\Provider\Statistics\GetInterventionStatisticsProvider;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource InterventionStatisticsResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'InterventionStatistics',
  description: 'Whole-organization intervention statistics for the list page KPI cards and the kanban column counters.',
  operations: [
    new Get(
      name: InterventionOperations::GET_INTERVENTION_STATISTICS,
      uriTemplate: '/interventions/statistics',
      input: false,
      output: InterventionStatisticsOutput::class,
      provider: GetInterventionStatisticsProvider::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Intervention'],
        summary: 'Get intervention statistics',
        description: 'Whole-organization snapshot: total, byStatus (all 7 status keys, zeros included), byPriority (all 4 priority keys, zeros included), overdue, dueSoon (48h window), bySite/byResponsible (top 10 each), averagePublicationDays. No filters in v1 — the whole organization is the question.',
        parameters: [
          new Parameter(name: 'organization', in: 'query', description: 'Organization IRI.', required: true, schema: ['type' => 'string']),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Intervention statistics retrieved'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Missing organization filter'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization outside the caller\'s scope — deliberately not 403, which would confirm it exists'),
        ],
      ),
    ),
  ],
)]
final class InterventionStatisticsResource
{
}
