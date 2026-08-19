<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post, Put};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Intervention\Presentation\Api\Dto\Input\{CreateInterventionWorkItemInput, UpdateInterventionWorkItemInput};
use Intervention\Presentation\Api\Dto\Output\InterventionWorkItemOutput;
use Intervention\Presentation\Api\Operation\InterventionOperations;
use Intervention\Presentation\Api\Processor\InterventionWorkItemProcessor;
use Intervention\Presentation\Api\Provider\InterventionWorkItemProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource InterventionWorkItemResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'InterventionWorkItem',
  operations: [
    new Post(name: InterventionOperations::CREATE_INTERVENTION_WORK_ITEM, uriTemplate: '/intervention-work-items', input: CreateInterventionWorkItemInput::class, output: InterventionWorkItemOutput::class, processor: InterventionWorkItemProcessor::class, security: "is_granted('ROLE_USER')"),
    new Put(name: InterventionOperations::PUT_INTERVENTION_WORK_ITEM, uriTemplate: '/intervention-work-items/{id}', read: false, input: CreateInterventionWorkItemInput::class, output: InterventionWorkItemOutput::class, processor: InterventionWorkItemProcessor::class, status: Response::HTTP_CREATED, security: "is_granted('ROLE_USER')"),
    new GetCollection(
      name: InterventionOperations::LIST_INTERVENTION_WORK_ITEMS,
      uriTemplate: '/intervention-work-items',
      output: InterventionWorkItemOutput::class,
      provider: InterventionWorkItemProvider::class,
      paginationEnabled: true,
      openapi: new Operation(parameters: [
        new Parameter(name: 'intervention', in: 'query', required: true, schema: ['type' => 'string']),
        new Parameter(name: 'assignee', in: 'query', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'source', in: 'query', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'action', in: 'query', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'status', in: 'query', required: false, schema: ['type' => 'string']),
      ]),
      security: "is_granted('ROLE_USER')",
    ),
    new Get(name: InterventionOperations::GET_INTERVENTION_WORK_ITEM, uriTemplate: '/intervention-work-items/{id}', output: InterventionWorkItemOutput::class, provider: InterventionWorkItemProvider::class, security: "is_granted('ROLE_USER')"),
    new Patch(name: InterventionOperations::UPDATE_INTERVENTION_WORK_ITEM, uriTemplate: '/intervention-work-items/{id}', read: false, input: UpdateInterventionWorkItemInput::class, output: InterventionWorkItemOutput::class, processor: InterventionWorkItemProcessor::class, security: "is_granted('ROLE_USER')"),
    new Delete(name: InterventionOperations::DELETE_INTERVENTION_WORK_ITEM, uriTemplate: '/intervention-work-items/{id}', read: false, input: false, output: false, processor: InterventionWorkItemProcessor::class, status: Response::HTTP_NO_CONTENT, security: "is_granted('ROLE_USER')"),
  ],
)]
final class InterventionWorkItemResource
{
}
