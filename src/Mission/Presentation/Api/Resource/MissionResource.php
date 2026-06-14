<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post, Put};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Mission\Presentation\Api\Dto\Input\{CreateMissionInput, UpdateMissionInput};
use Mission\Presentation\Api\Dto\Output\MissionOutput;
use Mission\Presentation\Api\Processor\MissionProcessor;
use Mission\Presentation\Api\Provider\MissionProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource MissionResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Mission',
  description: 'Field mission coordinating draft operational resources.',
  operations: [
    new Post(uriTemplate: '/missions', input: CreateMissionInput::class, output: MissionOutput::class, processor: MissionProcessor::class, security: "is_granted('ROLE_USER')"),
    new Put(name: 'mission_put', uriTemplate: '/missions/{id}', read: false, input: CreateMissionInput::class, output: MissionOutput::class, processor: MissionProcessor::class, status: Response::HTTP_CREATED, security: "is_granted('ROLE_USER')"),
    new GetCollection(
      uriTemplate: '/missions',
      output: MissionOutput::class,
      provider: MissionProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationItemsPerPage: 30,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(parameters: [
        new Parameter(name: 'organization', in: 'query', description: 'Organization IRI.', required: true, schema: ['type' => 'string']),
        new Parameter(name: 'responsible', in: 'query', description: 'Responsible member IRI.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'participant', in: 'query', description: 'Participant member IRI.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'type', in: 'query', description: 'Mission type.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'status', in: 'query', description: 'Mission status.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'site', in: 'query', description: 'Site IRI.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'dueAtAfter', in: 'query', description: 'Inclusive lower due-date bound.', required: false, schema: ['type' => 'string', 'format' => 'date-time']),
        new Parameter(name: 'dueAtBefore', in: 'query', description: 'Inclusive upper due-date bound.', required: false, schema: ['type' => 'string', 'format' => 'date-time']),
      ]),
    ),
    new Get(uriTemplate: '/missions/{id}', output: MissionOutput::class, provider: MissionProvider::class, security: "is_granted('ROLE_USER')"),
    new Patch(uriTemplate: '/missions/{id}', read: false, input: UpdateMissionInput::class, output: MissionOutput::class, processor: MissionProcessor::class, security: "is_granted('ROLE_USER')"),
    new Delete(uriTemplate: '/missions/{id}', read: false, input: false, output: false, processor: MissionProcessor::class, status: Response::HTTP_NO_CONTENT, security: "is_granted('ROLE_USER')"),
  ],
)]
final class MissionResource
{
}
