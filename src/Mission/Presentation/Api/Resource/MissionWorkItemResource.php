<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post, Put};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Mission\Presentation\Api\Dto\Input\{CreateMissionWorkItemInput, UpdateMissionWorkItemInput};
use Mission\Presentation\Api\Dto\Output\MissionWorkItemOutput;
use Mission\Presentation\Api\Processor\MissionWorkItemProcessor;
use Mission\Presentation\Api\Provider\MissionWorkItemProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource MissionWorkItemResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'MissionWorkItem',
  operations: [
    new Post(uriTemplate: '/mission-work-items', input: CreateMissionWorkItemInput::class, output: MissionWorkItemOutput::class, processor: MissionWorkItemProcessor::class, security: "is_granted('ROLE_USER')"),
    new Put(name: 'mission_work_item_put', uriTemplate: '/mission-work-items/{id}', read: false, input: CreateMissionWorkItemInput::class, output: MissionWorkItemOutput::class, processor: MissionWorkItemProcessor::class, status: Response::HTTP_CREATED, security: "is_granted('ROLE_USER')"),
    new GetCollection(
      uriTemplate: '/mission-work-items',
      output: MissionWorkItemOutput::class,
      provider: MissionWorkItemProvider::class,
      paginationEnabled: true,
      openapi: new Operation(parameters: [
        new Parameter(name: 'mission', in: 'query', required: true, schema: ['type' => 'string']),
        new Parameter(name: 'assignee', in: 'query', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'source', in: 'query', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'action', in: 'query', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'status', in: 'query', required: false, schema: ['type' => 'string']),
      ]),
      security: "is_granted('ROLE_USER')",
    ),
    new Get(uriTemplate: '/mission-work-items/{id}', output: MissionWorkItemOutput::class, provider: MissionWorkItemProvider::class, security: "is_granted('ROLE_USER')"),
    new Patch(uriTemplate: '/mission-work-items/{id}', read: false, input: UpdateMissionWorkItemInput::class, output: MissionWorkItemOutput::class, processor: MissionWorkItemProcessor::class, security: "is_granted('ROLE_USER')"),
    new Delete(uriTemplate: '/mission-work-items/{id}', read: false, input: false, output: false, processor: MissionWorkItemProcessor::class, status: Response::HTTP_NO_CONTENT, security: "is_granted('ROLE_USER')"),
  ],
)]
final class MissionWorkItemResource
{
}
