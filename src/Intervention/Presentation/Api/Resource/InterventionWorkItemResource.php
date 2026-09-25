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
    new Post(name: InterventionOperations::CREATE_INTERVENTION_WORK_ITEM, uriTemplate: '/intervention-work-items', input: CreateInterventionWorkItemInput::class, output: InterventionWorkItemOutput::class, processor: InterventionWorkItemProcessor::class, security: self::SECURITY_ROLE_USER),
    new Put(name: InterventionOperations::PUT_INTERVENTION_WORK_ITEM, uriTemplate: self::WORK_ITEM_URI_TEMPLATE, read: false, input: CreateInterventionWorkItemInput::class, output: InterventionWorkItemOutput::class, processor: InterventionWorkItemProcessor::class, status: Response::HTTP_CREATED, security: self::SECURITY_ROLE_USER),
    new GetCollection(
      name: InterventionOperations::LIST_INTERVENTION_WORK_ITEMS,
      uriTemplate: '/intervention-work-items',
      output: InterventionWorkItemOutput::class,
      provider: InterventionWorkItemProvider::class,
      paginationEnabled: true,
      parameters: [
        'intervention' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          required: true,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'intervention', in: 'query', required: true, schema: ['type' => 'string']),
        ),
        'assignee' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'assignee', in: 'query', required: false, schema: ['type' => 'string']),
        ),
        'prioritizeAssignee' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: 'Member IRI whose matching tasks are ordered first, without filtering out other assignees.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'prioritizeAssignee', in: 'query', description: 'Member IRI whose matching tasks are ordered first, without filtering out other assignees.', required: false, schema: ['type' => 'string']),
        ),
        'source' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'source', in: 'query', required: false, schema: ['type' => 'string']),
        ),
        'action' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'action', in: 'query', required: false, schema: ['type' => 'string']),
        ),
        'status' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'status', in: 'query', required: false, schema: ['type' => 'string']),
        ),
        'status[]' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'array', 'items' => ['type' => 'string']],
          description: 'Match any of these statuses before counting and paginating.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'status[]', in: 'query', description: 'Match any of these statuses before counting and paginating.', required: false, schema: ['type' => 'array', 'items' => ['type' => 'string']], style: 'form', explode: true),
        ),
        'search' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'search', in: 'query', required: false, schema: ['type' => 'string']),
        ),
      ],
      openapi: new Operation(parameters: []),
      security: self::SECURITY_ROLE_USER,
    ),
    new Get(name: InterventionOperations::GET_INTERVENTION_WORK_ITEM, uriTemplate: self::WORK_ITEM_URI_TEMPLATE, output: InterventionWorkItemOutput::class, provider: InterventionWorkItemProvider::class, security: self::SECURITY_ROLE_USER),
    new Patch(name: InterventionOperations::UPDATE_INTERVENTION_WORK_ITEM, uriTemplate: self::WORK_ITEM_URI_TEMPLATE, read: false, input: UpdateInterventionWorkItemInput::class, output: InterventionWorkItemOutput::class, processor: InterventionWorkItemProcessor::class, security: self::SECURITY_ROLE_USER),
    new Delete(name: InterventionOperations::DELETE_INTERVENTION_WORK_ITEM, uriTemplate: self::WORK_ITEM_URI_TEMPLATE, read: false, input: false, output: false, processor: InterventionWorkItemProcessor::class, status: Response::HTTP_NO_CONTENT, security: self::SECURITY_ROLE_USER),
  ],
)]
final class InterventionWorkItemResource
{
  // #region Constants
  private const string SECURITY_ROLE_USER = "is_granted('ROLE_USER')";

  private const string WORK_ITEM_URI_TEMPLATE = '/intervention-work-items/{id}';
  // #endregion
}
