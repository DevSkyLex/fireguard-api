<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post, Put};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Intervention\Presentation\Api\Dto\Input\{CreateInterventionInput, UpdateInterventionInput};
use Intervention\Presentation\Api\Dto\Output\InterventionOutput;
use Intervention\Presentation\Api\Processor\InterventionProcessor;
use Intervention\Presentation\Api\Provider\InterventionProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource InterventionResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Intervention',
  description: 'Field intervention coordinating draft operational resources.',
  operations: [
    new Post(uriTemplate: '/interventions', input: CreateInterventionInput::class, output: InterventionOutput::class, processor: InterventionProcessor::class, security: "is_granted('ROLE_USER')"),
    new Put(name: 'intervention_put', uriTemplate: '/interventions/{id}', read: false, input: CreateInterventionInput::class, output: InterventionOutput::class, processor: InterventionProcessor::class, status: Response::HTTP_CREATED, security: "is_granted('ROLE_USER')"),
    new GetCollection(
      uriTemplate: '/interventions',
      output: InterventionOutput::class,
      provider: InterventionProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationItemsPerPage: 30,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(parameters: [
        new Parameter(name: 'organization', in: 'query', description: 'Organization IRI.', required: true, schema: ['type' => 'string']),
        new Parameter(name: 'responsible', in: 'query', description: 'Responsible member IRI.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'participant', in: 'query', description: 'Participant member IRI.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'type', in: 'query', description: 'Intervention type.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'status', in: 'query', description: 'Intervention status.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'site', in: 'query', description: 'Site IRI.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'dueAtAfter', in: 'query', description: 'Inclusive lower due-date bound.', required: false, schema: ['type' => 'string', 'format' => 'date-time']),
        new Parameter(name: 'dueAtBefore', in: 'query', description: 'Inclusive upper due-date bound.', required: false, schema: ['type' => 'string', 'format' => 'date-time']),
      ]),
    ),
    new Get(uriTemplate: '/interventions/{id}', output: InterventionOutput::class, provider: InterventionProvider::class, security: "is_granted('ROLE_USER')"),
    new Patch(uriTemplate: '/interventions/{id}', read: false, input: UpdateInterventionInput::class, output: InterventionOutput::class, processor: InterventionProcessor::class, security: "is_granted('ROLE_USER')"),
    new Delete(uriTemplate: '/interventions/{id}', read: false, input: false, output: false, processor: InterventionProcessor::class, status: Response::HTTP_NO_CONTENT, security: "is_granted('ROLE_USER')"),
  ],
)]
final class InterventionResource
{
}
