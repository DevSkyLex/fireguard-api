<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post, Put};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Intervention\Presentation\Api\Dto\Input\{CreateInterventionChangeInput, UpdateInterventionChangeInput};
use Intervention\Presentation\Api\Dto\Output\InterventionChangeOutput;
use Intervention\Presentation\Api\Processor\InterventionChangeProcessor;
use Intervention\Presentation\Api\Provider\InterventionChangeProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource InterventionChangeResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'InterventionChange',
  operations: [
    new Post(uriTemplate: '/intervention-changes', input: CreateInterventionChangeInput::class, output: InterventionChangeOutput::class, processor: InterventionChangeProcessor::class, security: "is_granted('ROLE_USER')"),
    new Put(name: 'intervention_change_put', uriTemplate: '/intervention-changes/{id}', read: false, input: CreateInterventionChangeInput::class, output: InterventionChangeOutput::class, processor: InterventionChangeProcessor::class, status: Response::HTTP_CREATED, security: "is_granted('ROLE_USER')"),
    new GetCollection(
      uriTemplate: '/intervention-changes',
      output: InterventionChangeOutput::class,
      provider: InterventionChangeProvider::class,
      paginationEnabled: true,
      openapi: new Operation(parameters: [
        new Parameter(name: 'intervention', in: 'query', required: true, schema: ['type' => 'string']),
        new Parameter(name: 'resource', in: 'query', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'status', in: 'query', required: false, schema: ['type' => 'string']),
      ]),
      security: "is_granted('ROLE_USER')",
    ),
    new Get(uriTemplate: '/intervention-changes/{id}', output: InterventionChangeOutput::class, provider: InterventionChangeProvider::class, security: "is_granted('ROLE_USER')"),
    new Patch(uriTemplate: '/intervention-changes/{id}', read: false, input: UpdateInterventionChangeInput::class, output: InterventionChangeOutput::class, processor: InterventionChangeProcessor::class, security: "is_granted('ROLE_USER')"),
    new Delete(uriTemplate: '/intervention-changes/{id}', read: false, input: false, output: false, processor: InterventionChangeProcessor::class, status: Response::HTTP_NO_CONTENT, security: "is_granted('ROLE_USER')"),
  ],
)]
final class InterventionChangeResource
{
}
