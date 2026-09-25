<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Intervention\Presentation\Api\Dto\Input\{CreateInterventionRecurrenceInput, UpdateInterventionRecurrenceInput};
use Intervention\Presentation\Api\Dto\Output\InterventionRecurrenceOutput;
use Intervention\Presentation\Api\Operation\InterventionOperations;
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
      name: InterventionOperations::CREATE_INTERVENTION_RECURRENCE,
      uriTemplate: '/intervention-recurrences',
      input: CreateInterventionRecurrenceInput::class,
      output: InterventionRecurrenceOutput::class,
      processor: InterventionRecurrenceProcessor::class,
      status: Response::HTTP_CREATED,
      security: self::SECURITY_ROLE_USER,
    ),
    new GetCollection(
      name: InterventionOperations::LIST_INTERVENTION_RECURRENCES,
      uriTemplate: '/intervention-recurrences',
      output: InterventionRecurrenceOutput::class,
      provider: InterventionRecurrenceProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
      security: self::SECURITY_ROLE_USER,
      parameters: [
        'organization' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: 'Organization IRI.',
          required: true,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'organization', in: 'query', description: 'Organization IRI.', required: true, schema: ['type' => 'string']),
        ),
        'isActive' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'boolean'],
          description: 'Active-state filter.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'isActive', in: 'query', description: 'Active-state filter.', required: false, schema: ['type' => 'boolean']),
        ),
      ],
      openapi: new Operation(parameters: []),
    ),
    new Get(
      name: InterventionOperations::GET_INTERVENTION_RECURRENCE,
      uriTemplate: self::RECURRENCE_URI_TEMPLATE,
      output: InterventionRecurrenceOutput::class,
      provider: InterventionRecurrenceProvider::class,
      security: self::SECURITY_ROLE_USER,
    ),
    new Patch(
      name: InterventionOperations::UPDATE_INTERVENTION_RECURRENCE,
      uriTemplate: self::RECURRENCE_URI_TEMPLATE,
      read: false,
      input: UpdateInterventionRecurrenceInput::class,
      output: InterventionRecurrenceOutput::class,
      processor: InterventionRecurrenceProcessor::class,
      security: self::SECURITY_ROLE_USER,
    ),
    new Delete(
      name: InterventionOperations::DELETE_INTERVENTION_RECURRENCE,
      uriTemplate: self::RECURRENCE_URI_TEMPLATE,
      read: false,
      input: false,
      output: false,
      processor: InterventionRecurrenceProcessor::class,
      status: Response::HTTP_NO_CONTENT,
      security: self::SECURITY_ROLE_USER,
    ),
  ],
)]
final class InterventionRecurrenceResource
{
  // #region Constants
  private const string SECURITY_ROLE_USER = "is_granted('ROLE_USER')";

  private const string RECURRENCE_URI_TEMPLATE = '/intervention-recurrences/{id}';
  // #endregion
}
