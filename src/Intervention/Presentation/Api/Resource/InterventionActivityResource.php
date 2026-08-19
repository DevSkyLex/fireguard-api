<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Intervention\Presentation\Api\Dto\Input\CreateInterventionCommentInput;
use Intervention\Presentation\Api\Dto\Output\InterventionActivityOutput;
use Intervention\Presentation\Api\Operation\InterventionOperations;
use Intervention\Presentation\Api\Processor\InterventionActivityProcessor;
use Intervention\Presentation\Api\Provider\InterventionActivityProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource InterventionActivityResource.
 *
 * The intervention activity feed: member-authored comments and
 * system-recorded lifecycle events (creation, status transitions).
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'InterventionActivity',
  operations: [
    new GetCollection(
      name: InterventionOperations::LIST_INTERVENTION_ACTIVITIES,
      uriTemplate: '/interventions/{interventionId}/activities',
      output: InterventionActivityOutput::class,
      provider: InterventionActivityProvider::class,
      paginationEnabled: true,
      paginationItemsPerPage: 30,
      paginationClientItemsPerPage: true,
      openapi: new Operation(parameters: [
        new Parameter(name: 'interventionId', in: 'path', required: true, schema: ['type' => 'string']),
      ]),
      security: "is_granted('ROLE_USER')",
    ),
    new Post(
      name: InterventionOperations::CREATE_INTERVENTION_COMMENT,
      uriTemplate: '/interventions/{interventionId}/comments',
      input: CreateInterventionCommentInput::class,
      output: InterventionActivityOutput::class,
      processor: InterventionActivityProcessor::class,
      status: Response::HTTP_CREATED,
      security: "is_granted('ROLE_USER')",
    ),
  ],
)]
final class InterventionActivityResource
{
}
