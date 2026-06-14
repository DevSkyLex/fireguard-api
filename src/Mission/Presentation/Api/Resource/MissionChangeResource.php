<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post, Put};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Mission\Presentation\Api\Dto\Input\{CreateMissionChangeInput, UpdateMissionChangeInput};
use Mission\Presentation\Api\Dto\Output\MissionChangeOutput;
use Mission\Presentation\Api\Processor\MissionChangeProcessor;
use Mission\Presentation\Api\Provider\MissionChangeProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource MissionChangeResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'MissionChange',
  operations: [
    new Post(uriTemplate: '/mission-changes', input: CreateMissionChangeInput::class, output: MissionChangeOutput::class, processor: MissionChangeProcessor::class, security: "is_granted('ROLE_USER')"),
    new Put(name: 'mission_change_put', uriTemplate: '/mission-changes/{id}', read: false, input: CreateMissionChangeInput::class, output: MissionChangeOutput::class, processor: MissionChangeProcessor::class, status: Response::HTTP_CREATED, security: "is_granted('ROLE_USER')"),
    new GetCollection(
      uriTemplate: '/mission-changes',
      output: MissionChangeOutput::class,
      provider: MissionChangeProvider::class,
      paginationEnabled: true,
      openapi: new Operation(parameters: [
        new Parameter(name: 'mission', in: 'query', required: true, schema: ['type' => 'string']),
        new Parameter(name: 'resource', in: 'query', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'status', in: 'query', required: false, schema: ['type' => 'string']),
      ]),
      security: "is_granted('ROLE_USER')",
    ),
    new Get(uriTemplate: '/mission-changes/{id}', output: MissionChangeOutput::class, provider: MissionChangeProvider::class, security: "is_granted('ROLE_USER')"),
    new Patch(uriTemplate: '/mission-changes/{id}', read: false, input: UpdateMissionChangeInput::class, output: MissionChangeOutput::class, processor: MissionChangeProcessor::class, security: "is_granted('ROLE_USER')"),
    new Delete(uriTemplate: '/mission-changes/{id}', read: false, input: false, output: false, processor: MissionChangeProcessor::class, status: Response::HTTP_NO_CONTENT, security: "is_granted('ROLE_USER')"),
  ],
)]
final class MissionChangeResource
{
}
