<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Shared\Presentation\Api\Dto\Output\HealthOutput;
use Shared\Presentation\Api\Provider\HealthProvider;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource HealthResource.
 *
 * API resource for health check endpoint.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Health',
  description: 'Application health check',
  operations: [
    new Get(
      name: 'health_check',
      uriTemplate: '/health',
      output: HealthOutput::class,
      provider: HealthProvider::class,
      normalizationContext: ['groups' => ['health:read', 'health:read:details']],
      openapi: new Operation(
        tags: ['Health'],
        summary: 'Health Check',
        description: 'Returns the health status of the application and its dependencies (database, cache).',
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Application is healthy',
          ),
          HttpResponse::HTTP_SERVICE_UNAVAILABLE => new Response(
            description: 'Application is unhealthy - critical dependency failure',
          ),
        ],
      ),
    ),
  ],
)]
final class HealthResource
{
}
