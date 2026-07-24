<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Intervention\Presentation\Api\Dto\Input\{CreateInterventionRecurrenceInput, UpdateInterventionRecurrenceInput};
use Intervention\Presentation\Api\Dto\Output\InterventionRecurrenceOutput;
use Intervention\Presentation\Api\Processor\InterventionRecurrenceProcessor;
use Intervention\Presentation\Api\Provider\InterventionRecurrenceProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource InterventionRecurrenceResource.
 *
 * Organization-scoped recurring intervention schedules: a rule (frequency +
 * interval + anchor date, in a fixed timezone) that periodically
 * materializes an intervention template into a real intervention draft
 * through the recurring sweep (`MaterializeDueRecurrencesHandler`).
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'InterventionRecurrence',
  operations: [
    new Post(
      uriTemplate: '/intervention-recurrences',
      input: CreateInterventionRecurrenceInput::class,
      output: InterventionRecurrenceOutput::class,
      processor: InterventionRecurrenceProcessor::class,
      status: Response::HTTP_CREATED,
      security: "is_granted('ROLE_USER')",
    ),
    new GetCollection(
      uriTemplate: '/intervention-recurrences',
      output: InterventionRecurrenceOutput::class,
      provider: InterventionRecurrenceProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationItemsPerPage: 30,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(parameters: [
        new Parameter(name: 'organization', in: 'query', description: 'Organization IRI.', required: true, schema: ['type' => 'string']),
        new Parameter(name: 'isActive', in: 'query', description: 'Active-state filter.', required: false, schema: ['type' => 'boolean']),
      ]),
    ),
    new Get(
      uriTemplate: '/intervention-recurrences/{id}',
      output: InterventionRecurrenceOutput::class,
      provider: InterventionRecurrenceProvider::class,
      security: "is_granted('ROLE_USER')",
    ),
    new Patch(
      uriTemplate: '/intervention-recurrences/{id}',
      read: false,
      input: UpdateInterventionRecurrenceInput::class,
      output: InterventionRecurrenceOutput::class,
      processor: InterventionRecurrenceProcessor::class,
      security: "is_granted('ROLE_USER')",
    ),
    new Delete(
      uriTemplate: '/intervention-recurrences/{id}',
      read: false,
      input: false,
      output: false,
      processor: InterventionRecurrenceProcessor::class,
      status: Response::HTTP_NO_CONTENT,
      security: "is_granted('ROLE_USER')",
    ),
  ],
)]
final class InterventionRecurrenceResource
{
}
